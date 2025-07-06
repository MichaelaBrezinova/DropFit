import time
import os
import sys
import json
import argparse
import pandas as pd
from helpers import get_valid_column_indexes, linear_function,get_column_kth_moment,round_mean_and_error, remove_empty_columns, log_message,  is_positive_number, rename_duplicate_columns
import numpy as np
import matplotlib.pyplot as plt
import heapq
from mpl_toolkits.axes_grid1.inset_locator import inset_axes as i_a
from scipy.optimize import curve_fit
import matplotlib
matplotlib.use("Agg")

def get_plot_data(data, concentrations_table_metadata, k_to_test_for, debug_file=""):
    min_concentration = min(list(concentrations_table_metadata.keys()))
    max_concentration = max(list(concentrations_table_metadata.keys()))

    # Store data for plot with keys being k values
    plot_data = {}

    # For each k, we calculate x data (concentrations), corresponding ys (kth_moments ** (-1/k)), ys_std (std of kth_moments ** (-1/k)),
    # slope_error of the weighted linear regression on the given data and corresponding critical concentration
    for k in k_to_test_for:
        # Prepare dictionary of values for the given k
        plot_data[k] = {'x': [], 'y': [], 'y_std': [], 'slope_error': None, 'critical_concentration': None}
        # Calculate kth moment for every concentration's replicate (all columns) (independently)
        kth_moments = list(data.apply(lambda column: get_column_kth_moment(column, k=k), axis=0))
        # Calculate kth moment^(-1/k)for every concentration's replicate (all columns) (independently)
        kth_moments_to_minus_1_over_k = kth_moments ** (-1/k)

        # For each concentration, retrieve data on kth_moments_to_minus_1_over_k  of its replicates. 
        # Then calculate mean and std of these values. This will be representative data for the given
        # concentration. Then add this data to the data for the given k. 
        replicates_per_concentration = []
        for concentration, concentration_indexes in concentrations_table_metadata.items():
            replicates_per_concentration.append(len(concentration_indexes))
            relevant_k_moments_to_minus_1_over_k = [kth_moments_to_minus_1_over_k[index] for index in concentration_indexes]
            mean_of_kth_moments_to_minus_1_over_k = np.mean(relevant_k_moments_to_minus_1_over_k)
            std_of_kth_moments_to_minus_1_over_k= np.std(relevant_k_moments_to_minus_1_over_k)
            plot_data[k]['x'].append(concentration)
            plot_data[k]['y'].append(mean_of_kth_moments_to_minus_1_over_k)
            plot_data[k]['y_std'].append(std_of_kth_moments_to_minus_1_over_k)

        # NEW ADDITION TO HANDLE CASES OF ONLY 1 REPLICATE PER CONCENTRATION - Y_STD BEING 0 
        # For cases when there is only 1 replicate per concentration, y_std will be 0. This will give too much weight
        # to this concentration which is not ideal. Correct this by calculating an approximate y_std calculated from 
        # y_stds of other concentrations that have more replicates. Using scaling of the concentrations' means to get
        # values appropriate for the given concentration. 
        replicates_per_concentration = np.array(replicates_per_concentration)
        y_std = np.array(plot_data[k]['y_std'])
        indices_to_replace = (y_std == 0) & (replicates_per_concentration == 1)
        if len(y_std[~indices_to_replace])!=0:
            replacement_scaler = np.mean(y_std[~indices_to_replace]/np.abs(np.array(plot_data[k]['y']))[~indices_to_replace])
            y_std[indices_to_replace] = replacement_scaler * np.abs(np.array(plot_data[k]['y']))[indices_to_replace]
        # in case there are no concentrations with more replicates, set the y_st to 0.001 (small value)
        else:
            replacement_value = 0.001
            y_std[indices_to_replace] = replacement_value
        
        plot_data[k]['y_std'] = y_std.tolist()

        # Calculate linear regression fit on the datapoints, using y_stds to provide weight for the linear regression. 
        popt, pcov = curve_fit(linear_function, plot_data[k]['x'], plot_data[k]['y'], sigma=plot_data[k]['y_std'])

        # # DEPRECATED, HANDLING OF ERROR IN COVARIANCE CALCULATION INTRODUCED BELOW.
        # # Return empty map if covariance could not be calculated. This might mean that too few concentrations
        # # were provided.
        # if np.any(np.isinf(pcov)):
        #     return {}
        #
        # slope_error, intercept_error = np.sqrt(np.diag(pcov)) 
        
        # Handling of only 2 concentrations resulting in slope errors 0 due to
        # perfect linear fit. This, however, should not change result as for different ks, this the slope_error be 0 instead of inf, 
        # and they will be all the same so nothing changes in selection of ks to consider.
        if np.any(np.isinf(pcov)) and len(concentrations_table_metadata)==2:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_plot_data): Pcov had inf values, there are only 2 concentrations. Slope error setting to 0")
            slope_error = 0
        # Handling of any other error with calculating pcov matrix.
        elif np.any(np.isinf(pcov)):
            if debug_file:
                log_message(debug_file, f"DEBUG (get_plot_data): Pcov had inf values, unknown error. Returning empty dictionary")
            return {}
        # Retrieve slope errors in all other cases.
        else:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_plot_data): Pcov had normal values, getting slope_error from the pcov.")
            slope_error, _ = np.sqrt(np.diag(pcov)) 

        if debug_file:
                log_message(debug_file, f"DEBUG (get_plot_data): Slope error is {slope_error}")

        slope, intercept = popt
        
        plot_data[k]['slope_error'] = slope_error

        # Critical concentration is calculated as -intercept/slope (calculated from the intercept of x axis)
        plot_data[k]['critical_concentration'] = -intercept/slope

        # Get data for the line fit that uses intercept and slope calculated using linear regression. 
        line_fit_min_x = min(min_concentration, -intercept/slope)
        line_fit_max_x = max(max_concentration, -intercept/slope)
        line_fit_xs = np.linspace(line_fit_min_x,line_fit_max_x, 100)

        plot_data[k]['line_fit_xs'] = line_fit_xs
        plot_data[k]['line_fit_ys'] = slope*line_fit_xs + intercept

    return plot_data

def plot_and_calculate_critical_concentration(plot_data, k_to_consider, directory_to_store, out_id, debug_file=""):
    fig, ax = plt.subplots(figsize=(10, 8))
    # Create inset axes for mini plot inside the main plot
    inset_axes = i_a(ax, width="30%", height="30%", loc='upper right')

    if debug_file:
        log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Starting the calculations")
    
    critical_concentrations_to_consider = [plot_data[k]['critical_concentration'] for k in k_to_consider]

    if debug_file:
        log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Critical_concentrations to consider: {str(critical_concentrations_to_consider)}")

    # Plot main scatter plot with error bars and line plot
    for k in k_to_consider:
        scatter_plot = ax.scatter(plot_data[k]['x'], plot_data[k]['y'], label=f'k={k}')
        color = scatter_plot.get_facecolor()[0]  # Extract first RGBA tuple correctly
        ax.plot(plot_data[k]['line_fit_xs'], plot_data[k]['line_fit_ys'], color=color)

        # Plot mini plot inside the inset axes
        inset_axes.errorbar(plot_data[k]['x'], plot_data[k]['y'], yerr=plot_data[k]['y_std'], fmt=',', color=color, capsize=2)

    # Calculate the final critical concentration (mean + std)
    critical_concentration_mean = np.mean(critical_concentrations_to_consider)
    critical_concentration_std = np.std(critical_concentrations_to_consider)

    if debug_file:
        log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Critical concentration mean: {str(critical_concentration_mean)}")
        log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Critical concentration std: {str(critical_concentration_std)}")

    ax.errorbar(critical_concentration_mean, 0, xerr=critical_concentration_std, fmt='o', color='red', markersize=12)
    ax.legend(loc='lower left', fontsize=16)
    ax.set_title("Estimation of the critical concentration", fontsize=22)
    ax.set_xlabel(r'$\rho $', fontsize=20)
    ax.set_ylabel(r'$\langle s^k \rangle^{-1/k}$', fontsize=20)

    inset_axes.set_xticks([])
    inset_axes.set_xticklabels([])
    inset_axes.set_xlim(ax.get_xlim())
    inset_axes.set_yticks([])
    inset_axes.set_yticklabels([])
    inset_axes.set_ylim(ax.get_ylim())

    if debug_file:
        log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Finished plot settings")

    # Roud the critical concentration and std (taking significant figures of std into the account). Prepare text expression for the mean +/- std
    rounded_mean_critical_concentration, rounded_std_critical_concentration = round_mean_and_error(critical_concentration_mean, critical_concentration_std)
    
    # If std is a whole number, display it as a whole number without significant figures (ie 30 instead of 30.0) to not confuse the user
    if rounded_std_critical_concentration == int(rounded_std_critical_concentration):
        critical_concentration_expression = str(int(rounded_mean_critical_concentration)) + ' ± ' + str(int(rounded_std_critical_concentration))
    else: 
        critical_concentration_expression = str(rounded_mean_critical_concentration) + ' ± ' + str(rounded_std_critical_concentration)
    if debug_file:
        log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Critical concentration expression: {str(critical_concentration_expression)}")

    if rounded_mean_critical_concentration > 0 and rounded_std_critical_concentration >= 0 and rounded_mean_critical_concentration > rounded_std_critical_concentration:
        
        if debug_file:
            log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Starting to save critical concentration and plot")
        
        # Save concentration value to a file
        with open(f"{directory_to_store}/{out_id}_concentration.txt", "w") as file:
            # Write the string to the file
            file.write(critical_concentration_expression)

        # Save concentration plot to a file
        try:
            plt.savefig(f"{directory_to_store}/{out_id}_concentration_plot.png", dpi=300, bbox_inches='tight')
            if debug_file:
                log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Saved concentration plot figure")
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Error saving plot figure with exception {e}")
            return "","","", "Error saving the plot figure, try again later, please."

        return critical_concentration_expression, rounded_mean_critical_concentration, rounded_std_critical_concentration, ""

    else:
        if debug_file:
            log_message(debug_file, f"DEBUG (plot_and_calculate_critical_concentration): Error with critical concentration calculation. The result {critical_concentration_expression} does not seem plausible. Please, check if your file contains the right values and has the right format.")
        return "","","", f"Error with critical concentration calculation. The result {critical_concentration_expression} does not seem plausible. Please, check if your file contains the right values and has the right format (as per the example)."

def run_calculation(data_path, directory_to_store, out_id, do_k_optim=True, ks=np.array([]), concentrations_to_omit = np.array([]), debug_file=""):
    try:
        # Try to load the CSV file
        data = pd.read_csv(data_path)
    except Exception as e:
        # Return error if loading the CSV file fails
        return f"Error loading the CSV file. Please, check the format of your file."

    if data.empty:
        return f"Error with the CSV file. There are no valid concentration data. Please, check if your file has the right structure and contains right values."

    if debug_file:
        log_message(debug_file, f"DEBUG (run_calculation): csv file loaded, with {str(len(data))} rows")

    # Load header separately to handle replicates (duplicate column names)
    with open(data_path, 'r') as f:
        header = f.readline().strip().split(',')
    
    header = [col if col.strip() != '' else 'Unnamed' for col in header]

    # If the header entry (column) is not a number larger than 0, rename it to Unnamed. All column entries should be numbers
    # since they represent concentrations
    header = [col if col == 'Unnamed' or is_positive_number(col) else 'Unnamed' for col in header]
    has_numeric_columns = any(is_positive_number(col) for col in header if col != 'Unnamed')
    
    if not has_numeric_columns:
       return f"Error with the CSV file. There are no valid column headers - no numerical concentrations. Please, check if your file has the right structure and contains right values."
   
    header_with_duplicates_handled = rename_duplicate_columns(header)
    data.columns = header_with_duplicates_handled
    # Remove all unnamed columns
    data = data.loc[:, ~data.columns.str.startswith('Unnamed')]

    # Remove nonnumeric values and remove <=0 
    data = data.apply(pd.to_numeric, errors='coerce')
    data = data[data>0]
    # Remove empty columns - this should take care of also columns that might have have negative/all zero values
    data = remove_empty_columns(data)
    if data.empty:
        return f"Error with the CSV file. There are no valid concentration data. Please, check if your file has the right structure and contains right values."
    
    try:
        if debug_file:
            log_message(debug_file, "DEBUG (run_calculation): Starting run_calculation core eval")

        # Get valid column indexes for corresponding concentrations from the loaded data. 
        # Get also concentrations usage (which concentrations are being used for calculations)
        concentrations_table_metadata, concentrations_usage = get_valid_column_indexes(data, concentrations_to_omit)
        
        if debug_file:
            log_message(debug_file, f"DEBUG (run_calculation): concentration table metadata: {str(concentrations_table_metadata)}")

        if(len(concentrations_table_metadata)<2):
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error with critical concentration calculation - Too few ({len(concentrations_table_metadata)}) concentrations observed. Try to increase number of concentrations observed (at least 2, ideally 3 and more).")
            return f"Error with critical concentration calculation - Too few ({len(concentrations_table_metadata)}) concentrations observed. Try to increase number of concentrations observed (at least 2, ideally 3 and more)."
        
        ## DEPRECATED: r=Removed check as handling of 2 cases was introduced.
        # if(len(concentrations_table_metadata)<3):
        #     return f"Error with critical concentration calculation - Too few ({len(concentrations_table_metadata)}) concentrations observed. Try to increase number of concentrations observed (at least 3)."
        

        # Define k values to test for
        if(do_k_optim):
            k_to_test_for = np.arange(0.25, 2.25, 0.25)
        else:
            k_to_test_for = ks
        
        # Get plot data
        plot_data = get_plot_data(data, concentrations_table_metadata, k_to_test_for, debug_file)
        
        if debug_file:
            log_message(debug_file, f"DEBUG (run_calculation): Plot data length: {str(len(plot_data))}")

        if len(plot_data)==0:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error with critical concentration calculation - calculating covariance matrices. Please, check if your file has the right structure and contains right values.")    
            # return f"Error with critical concentration calculation - calculating covariance matrices. Try increasing number of concentrations observed (currently it is {len(concentrations_table_metadata)}). "
            return f"Error with critical concentration calculation - calculating covariance matrices. Please, check if your file has the right structure and contains right values."
        
        if do_k_optim:
            # Perform k optimization
            slope_errors = [plot_data[k]['slope_error'] for k in plot_data.keys()]
            indices_k_to_consider = [slope_errors.index(i) for i in heapq.nsmallest(4, slope_errors)]
            smallest_errors = heapq.nsmallest(4, slope_errors)
            # Find all indices corresponding to the 4 smallest values
            indices_k_to_consider = [i for i, error in enumerate(slope_errors) if error in smallest_errors]
            k_to_consider = sorted([list(plot_data.keys())[index] for index in indices_k_to_consider])
        else:
            k_to_consider = list(plot_data.keys())

        if debug_file:
            log_message(debug_file, f"DEBUG (run_calculation): k to consider: {str(k_to_consider)}")

        # Plot and calculate critical concentration
        critical_concentration_expression, critical_concentration_mean, critical_concentration_std ,error_output = plot_and_calculate_critical_concentration(plot_data, k_to_consider, directory_to_store, out_id, debug_file)

        if error_output:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): error output: {str(error_output)}")
            return error_output

    except Exception as e:
        if debug_file:
                log_message(debug_file, f"Error during critical concentration calculation. Please, check if your file has the right structure and contains right values.")
        # Return error if any other part of the code fails
        return f"Error during critical concentration calculation. Please, check if your file has the right structure and contains right values."

    results_and_metadata_for_frontend = {
        "critical_concentration_expression": critical_concentration_expression,
        "critical_concentration_mean": critical_concentration_mean,
        "critical_concentration_std": critical_concentration_std,
        "concentrations_usage": concentrations_usage,
        "k_to_consider": k_to_consider
    }

    with open(f"{directory_to_store}/{out_id}_results_and_metadata_for_frontend.json", "w") as json_file:
        json.dump(results_and_metadata_for_frontend, json_file, indent=2)

    # Return success (empty) message if everything is executed without errors
    return ""
    

# Create the argument parser
parser = argparse.ArgumentParser(description='Description of your program.')
parser.add_argument('--data_path', type=str, help='Path to the data table')
parser.add_argument('--directory_to_store', type=str, help='Path to the directory to store')
parser.add_argument('--out_id', type=str, help='ID for the files to uniqely identify them')
parser.add_argument('--manual_ks', type=str, nargs='?', default='', help='String of k values separated by commas. If not specified, automatic k selection is made.')
parser.add_argument('--concentrations_to_omit', type=str, nargs='?', default='', help='String of concentrations separated by commas. If not specified, all concentrations are used.')
parser.add_argument('--debug', action='store_true', help='Enable debug mode')

args = parser.parse_args()
data_path = args.data_path
directory_to_store = args.directory_to_store
out_id = args.out_id
manual_ks_str = args.manual_ks
concentrations_to_omit_str = args.concentrations_to_omit
debug = args.debug

if manual_ks_str:
    do_k_optim = False
    manual_ks = np.array(manual_ks_str.split(','), dtype=float)
else:
    do_k_optim = True
    manual_ks = np.array([])

if concentrations_to_omit_str:
    concentrations_to_omit = np.array(concentrations_to_omit_str.split(','), dtype=float)
else:
    concentrations_to_omit = np.array([])

if debug:  
    debug_file=f"{directory_to_store}/{out_id}_debug_get_critical_concentration_and_plot.log"
else: 
    debug_file=""

result = run_calculation(data_path, directory_to_store, out_id, do_k_optim, manual_ks, concentrations_to_omit, debug_file)
print(result)
# print('hellothisispython')
with open(f"{directory_to_store}/{out_id}_get_critical_concentration_and_plot_error.txt", "w") as file:
    # Write the string to the file
    file.write(result)
