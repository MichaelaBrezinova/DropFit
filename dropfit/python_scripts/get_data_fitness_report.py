import time
import os
import sys
import json
import argparse
import math
import pandas as pd
from helpers import run_data_sanity_check, remove_empty_columns,get_valid_column_indexes, get_kde,get_histogram_data, get_phi_exponent_calculation_data, get_alpha_exponent_calculation_data, round_mean_and_error, is_positive_number, rename_duplicate_columns
import numpy as np
import matplotlib.pyplot as plt
from mpl_toolkits.axes_grid1.inset_locator import inset_axes as i_a
from scipy.stats import gaussian_kde
# import seaborn as sns
import matplotlib
matplotlib.use("Agg")

def log_message(debug_file, message):
    with open(debug_file, "a") as file:
        file.write(message + "\n")

def get_kdes_plot(concentrations_table_metadata, cleaned_data, directory_to_store, out_id, debug_file="", use_kde=True):
    fig, ax = plt.subplots(figsize=(10, 8))
    min_x = float('inf')     # positive infinity
    max_x = float('-inf')    # negative infinity
    x_cutoffs = []           # stores last non-zero density x-values for each concentration

    if use_kde:
        # Get bandwidth for KDE from all data so then it is consistent across concentrations
        all_data = cleaned_data.values.flatten()
        pooled_data = all_data[~np.isnan(all_data)]
        pooled_data = pooled_data[pooled_data > 0]
        global_bandwidth = gaussian_kde(pooled_data).scotts_factor()

    # Getting the KDE for each concentration
    for concentration, column_indices in concentrations_table_metadata.items():
        if debug_file:
            log_message(debug_file, f"DEBUG (get_kdes_plot): Starting calculations for concentration {concentration}")

        concentration_relevant_columns = cleaned_data.iloc[:, column_indices]
        concentration_data = np.array(concentration_relevant_columns.values.flatten().tolist())
        concentration_data = concentration_data[~np.isnan(concentration_data)]
        concentration_data = concentration_data[concentration_data > 0]

        if use_kde:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_kdes_plot): Performing KDE ")
            x, y, original_x, original_y = get_kde(concentration_data, bw_method=global_bandwidth)
            plt.plot(x, y, label=concentration)
            plt.scatter(original_x, original_y, s=5)

            # Track the last x-value where density is > 0.01
            valid_indices = np.where(y > 0.01)[0]
            if len(valid_indices) > 0:
                last_x = x[valid_indices[-1]]
                x_cutoffs.append(last_x)

            # Update min and max for fallback
            if min(x) < min_x:
                min_x = min(x)
            if max(x) > max_x:
                max_x = max(x)
        else:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_kdes_plot): Performing histogram calculations ")
            x, y = get_histogram_data(concentration_data)
            plt.plot(x, y, label=concentration)

            # Finding min and max for adjusting the plot's x limits calculation
            if min(x) < min_x:
                min_x = min(x)
            if max(x) > max_x:
                max_x = max(x)

    if debug_file:
        log_message(debug_file, f"DEBUG (get_kdes_plot): Finished processing KDEs and plotting")

    plt.title('Droplet size distributions', fontsize=22)
    plt.xlabel(r'$s$', fontsize=20)
    plt.ylabel(r'$P(s)$', fontsize=20)
    plt.legend(fontsize=16)

    # Adjust x-limits based on density cutoff logic
    if x_cutoffs:
        x_cutoff_final = np.median(x_cutoffs)
        xlim_right = x_cutoff_final
    else:
        xlim_right = max_x

    # Padding on both sides
    axis_padding = abs(xlim_right - min_x) / 5
    plt.xlim(min_x - axis_padding, xlim_right + axis_padding)

    plt.savefig(f"{directory_to_store}/{out_id}_kdes_plot.png", dpi=300, bbox_inches='tight')
    plt.clf()
    return

def get_collapse_concentrations_plot(concentrations_table_metadata, cleaned_data, critical_concentration, directory_to_store, out_id, debug_file="", use_kde=True):
    fig, ax = plt.subplots(figsize=(10, 8))
    min_x = float('inf')     # positive infinity
    max_x = float('-inf')    # negative infinity
    x_cutoffs = []           # stores last non-zero density x-values for each concentration

    if use_kde:
        # Get bandwith for KDE from all data so then it is consistent across concentrations
        all_data = cleaned_data.values.flatten()
        # Remove NaNs and non-positive values
        pooled_data = all_data[~np.isnan(all_data)]
        pooled_data = pooled_data[pooled_data > 0]
        global_bandwidth = gaussian_kde(pooled_data).scotts_factor()

    # Getting the collapsed KDE for each concentration
    for concentration, column_indices in concentrations_table_metadata.items():
        if(debug_file):
            log_message(debug_file, f"DEBUG (get_collapse_concentrations_plot): Starting calculations for concentration {concentration}")
        concentration_relevant_columns = cleaned_data.iloc[:, column_indices]
        concentration_data = np.array(concentration_relevant_columns.values.flatten().tolist())
        concentration_data = concentration_data[~np.isnan(concentration_data)]
        concentration_data = concentration_data[concentration_data >0]
        # concentration_data_scaled = concentration_data*(1 - concentration/critical_concentration)

        if use_kde:
            if(debug_file):
                log_message(debug_file, f"DEBUG (get_collapse_concentrations_plot): Performing KDE ")
            x, y, original_x, original_y = get_kde(concentration_data, bw_method =global_bandwidth)
            # x, y, original_x, original_y = get_kde(concentration_data_scaled)

            # Scaling x to perform the collapse
            x_scaled = x*(1 - concentration/critical_concentration)
            original_x_scaled = original_x*(1 - concentration/critical_concentration)

            # Correcting y to make sure area under the curve is 1
            y_scaled = y/(1 - concentration/critical_concentration)
            original_y_scaled = original_y/(1 - concentration/critical_concentration)
            
            plt.plot(x_scaled, y_scaled, label=concentration)
            plt.scatter(original_x_scaled, original_y_scaled, s=5)

            # Track the last x-value where density is > 0.01
            valid_indices = np.where(y_scaled > 0.01)[0]
            if len(valid_indices) > 0:
                last_x = x_scaled[valid_indices[-1]]
                x_cutoffs.append(last_x)

            # Finding min and max for adjusting the plot's x limits calculation
            if min(x)<min_x:
                min_x=min(x)
            if max(x)>max_x:
                max_x=max(x)
        else:
            if(debug_file):
                log_message(debug_file, f"DEBUG (get_collapse_concentrations_plot): Performing histogram calculation")
            x, y = get_histogram_data(concentration_data)
            # x, y, original_x, original_y = get_kde(concentration_data_scaled)
            
            # Scaling x to perform the collapse
            x_scaled = x*(1 - concentration/critical_concentration)
            # Correcting y to make sure area under the curve is 1
            y_scaled = y/(1 - concentration/critical_concentration)

            plt.plot(x_scaled, y_scaled, label=concentration)

            # Finding min and max for adjusting the plot's x limits calculation
            if min(x)<min_x:
                min_x=min(x)
            if max(x)>max_x:
                max_x=max(x)
        

    plt.title(rf'Collapse of droplet size distributions ($\rho_c = {critical_concentration}$)', fontsize=22)
    plt.xlabel(r'$s (1 - \rho/ \rho_c)$', fontsize=20)
    plt.ylabel(r'$P(s(1 - \rho/ \rho_c))$', fontsize=20)
    plt.legend(fontsize=16)

    if(debug_file):
            log_message(debug_file, f"DEBUG (get_collapse_concentrations_plot): Finished processing concentration collapse and plotting.")
    
    # Adjust x-limits based on density cutoff logic
    if x_cutoffs:
        x_cutoff_final = np.median(x_cutoffs)
        xlim_right = x_cutoff_final
    else:
        xlim_right = max_x

    # Padding on both sides
    axis_padding = abs(xlim_right - min_x) / 5
    plt.xlim(min_x - axis_padding, xlim_right + axis_padding)
    plt.savefig(f"{directory_to_store}/{out_id}_collapse_concentration_plot.png", dpi=300, bbox_inches='tight')
    plt.clf()
    return

def get_alpha_critical_exponent(concentrations_table_metadata, data, critical_concentration, directory_to_store, out_id, debug_file):
    plot_data, error = get_alpha_exponent_calculation_data(concentrations_table_metadata, data, critical_concentration)
    if error: 
        if debug_file:
            log_message(debug_file, f"DEBUG (get_alpha_critical_exponent): Error when preparing data for alpha critical exponent calculation. Error: {error}")
        return -1, -1, "Error when preparing data for alpha critical exponent calculation. Please check the data and critical concentration and try again."
    
    fig, ax = plt.subplots(figsize=(10, 8))
    inset_axes = i_a(ax, width="30%", height="30%", loc='lower right')

    # Plot scatter and fitted line
    ax.scatter(plot_data['x'], plot_data['y'])
    label_text = rf"Fit: $y = {plot_data['slope']:.2f}x + {plot_data['intercept']:.2f}$"
    ax.plot(plot_data['x_fit'], plot_data['y_fit'], color='black', label=label_text)



    # Plot mini plot inside the inset axes
    inset_axes.errorbar(plot_data['x'], plot_data['y'], yerr=plot_data['y_std'], fmt=',', capsize=2)

    inset_axes.set_xticks([])
    inset_axes.set_xticklabels([])
    inset_axes.set_xlim(ax.get_xlim())
    inset_axes.set_yticks([])
    inset_axes.set_yticklabels([])
    inset_axes.set_ylim(ax.get_ylim())

    # Plot styling
    ax.set_title(
        rf'Determination of $\alpha$ critical exponent' + '\n' +
        rf'for $\rho_c = {critical_concentration:}$',
        fontsize=22
    )
    ax.set_xlabel(r'$-ln(1 - \rho/ \rho_c)$', fontsize=20)
    ax.set_ylabel(r'$ln(\langle s \rangle)$', fontsize=20)
    ax.legend(fontsize=16)

    # Save concentration plot to a file
    try:
        plt.savefig(f"{directory_to_store}/{out_id}_alpha_critical_exponent_plot.png", dpi=300, bbox_inches='tight')
        if debug_file:
            log_message(debug_file, f"DEBUG (get_alpha_critical_exponent): Succesfully saved the alpha critical exponent plot figure")
    except Exception as e:
        if debug_file:
            log_message(debug_file, f"DEBUG (get_alpha_critical_exponent): Error saving alpha critical exponent plot figure with exception {e}.")
        return -1, -1, "Error saving the plot figure for calculation of the alpha critical exponent. Try again later,please."

    plt.clf()

    if debug_file:
            log_message(debug_file, f"DEBUG (get_alpha_critical_exponent): Calculated m: {plot_data['slope']} with m_std_err: {plot_data['slope_error']}.")
    return plot_data['slope'], plot_data['slope_error'], ""

def get_phi_critical_exponent(concentrations_table_metadata, data, critical_concentration, ks, directory_to_store, out_id, debug_file):
    plot_data, error = get_phi_exponent_calculation_data(concentrations_table_metadata, data, critical_concentration, ks)
    if error: 
        if debug_file:
            log_message(debug_file, f"DEBUG (get_phi_critical_exponent): Error when preparing data for phi critical exponent calculation. Error: {error}")
        return -1, -1, "Error when preparing data for phi critical exponent calculation. Please check the data and critical concentration and try again."

    fig, ax = plt.subplots(figsize=(10, 8))
    # Create inset axes for mini plot inside the main plot
    inset_axes = i_a(ax, width="30%", height="30%", loc='lower right')
    # Plot main scatter plot with error bars and line plot
    slopes = []
    slope_errors = []
    for k in ks:
        scatter_plot = ax.scatter(plot_data[k]['x'], plot_data[k]['y'], label=f'k={k}')
        color = scatter_plot.get_facecolor()[0]  # Extract first RGBA tuple correctly
        ax.plot(plot_data[k]['line_fit_xs'], plot_data[k]['line_fit_ys'], color=color)

        # Plot mini plot inside the inset axes
        inset_axes.errorbar(plot_data[k]['x'], plot_data[k]['y'], yerr=plot_data[k]['y_std'], fmt=',', color=color, capsize=2)
        slopes.append(plot_data[k]['slope'])
        slope_errors.append(plot_data[k]['slope_error'])

    inset_axes.set_xticks([])
    inset_axes.set_xticklabels([])
    inset_axes.set_xlim(ax.get_xlim())
    inset_axes.set_yticks([])
    inset_axes.set_yticklabels([])
    inset_axes.set_ylim(ax.get_ylim())

    ax.legend(loc='upper left', fontsize=16)
    ax.set_title(
        rf'Determination of $\phi$ critical exponent' + '\n' +
        rf'for $\rho_c = {critical_concentration:}$',
        fontsize=22
    )
    ax.set_xlabel(r'$-ln(1 - \rho/ \rho_c)$', fontsize=20)
    ax.set_ylabel(r'$ln(\langle s^{k+1} \rangle / \langle s^{k} \rangle)$', fontsize=20)

    # Save concentration plot to a file
    try:
        plt.savefig(f"{directory_to_store}/{out_id}_phi_critical_exponent_plot.png", dpi=300, bbox_inches='tight')
        if debug_file:
            log_message(debug_file, f"DEBUG (get_phi_critical_exponent): Successfully saved phi critical exponent plot figure.")
    except Exception as e:
        if debug_file:
            log_message(debug_file, f"DEBUG (get_phi_critical_exponent): Error saving phi critical exponent plot figure with exception {e}")
        return -1, -1, "Error saving the plot figure for calculation of the phi critical exponent. Try again later,please."
    plt.clf()
    # Calculation of phi (aka slope of the fitted lines) using weighted average
    if any(x == 0 for x in slope_errors):
        mean_slope = np.mean(slopes)
        if debug_file:
                log_message(debug_file, f"DEBUG (get_phi_critical_exponent): Output of phi calculation is - phi: {mean_slope}, phi_std_err: 0")
        return mean_slope, 0, ""
    else:
        weights = 1 / np.square(slope_errors)
        weighted_mean_slope = np.average(slopes, weights=weights)
        weighted_std_error = np.sqrt(1 / np.sum(weights))
        if debug_file:
                log_message(debug_file, f"DEBUG (get_phi_critical_exponent): Output of phi calculation is - phi: {weighted_mean_slope}, phi_std_err: {weighted_std_error}")
        return weighted_mean_slope, weighted_std_error, ""

def run_calculation(data_path, directory_to_store, out_id, critical_concentration, ks, concentrations_to_omit = np.array([]), debug_file=""):
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

    # If the header entry (column) is not a number larger than 0, rename it to NotSuitable. All column entries should be numbers
    # since they represent concentrations
    header = [col if col == 'Unnamed' or is_positive_number(col) else 'NotSuitable' for col in header]

    has_numeric_columns = any(col not in ['Unnamed', 'NotSuitable'] for col in header)
    
    if not has_numeric_columns:
       return f"Error with the CSV file. There are no valid column headers - no numerical concentrations. Please, check if your file has the right structure and contains right values."
   
    header_with_duplicates_handled = rename_duplicate_columns(header)
    data.columns = header_with_duplicates_handled
    # Remove all unnamed columns
    cleaned_data = data.loc[:, ~(
        data.columns.str.startswith('Unnamed') | data.columns.str.startswith('NotSuitable')
    )]

    # Remove nonnumeric values and remove <=0 
    cleaned_data = cleaned_data.apply(pd.to_numeric, errors='coerce')
    cleaned_data = cleaned_data[cleaned_data>0]
    # Remove empty columns - this should take care of also columns that might have have negative/all zero values
    cleaned_data = remove_empty_columns(cleaned_data)
    if cleaned_data.empty:
        return f"Error with the CSV file. There are no valid concentration data. Please, check if your file has the right structure and contains right values."

    try:
        if debug_file:
            log_message(debug_file, "DEBUG (run_calculation): Starting run_calculation core eval")
        

        # Prepare data for plotting and calculations - group columns by concentrations and save which concentrations are in use
        concentrations_table_metadata, _  = get_valid_column_indexes(cleaned_data, concentrations_to_omit)

        if(len(concentrations_table_metadata)<2):
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error with critical concentration calculation - Too few ({len(concentrations_table_metadata)}) concentrations observed. Try to increase number of concentrations observed (at least 2, ideally 3 and more).")
            return f"Error with critical concentration calculation - Too few ({len(concentrations_table_metadata)}) concentrations observed. Try to increase number of concentrations observed (at least 2, ideally 3 and more)."
        
        
        # Get the KDE plot
        try:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Generating the KDE plot")
            get_kdes_plot(concentrations_table_metadata, cleaned_data, directory_to_store, out_id, debug_file, use_kde=True)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation):  Error while preparing the KDE plot with exception {e}.")
            # Return error if any other part of the code fails
            return f"Error while preparing the collapse figures. Please, check you data and try again."

        # Get the critical concentration collapse plot
        try: 
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Generating the plot of collapse of concentrations")
            get_collapse_concentrations_plot(concentrations_table_metadata, cleaned_data, critical_concentration, directory_to_store, out_id, debug_file, use_kde=True)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation):  Error while preparing the critical collapse plot with exception {e}.")
            # Return error if any other part of the code fails
            return f"Error while preparing the collapse figures. Please, check you data and try again."
        
        # Get the alpha critical exponent plot and calculations for m (slope)
        try:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Generating plot for the alpha critical exponent and calculating m (needed for calculating alpha)")
            m_alpha_critical_exponent_calculation, m_std_alpha_critical_exponent_calculation, error_alpha_critical_exponent_calculation = get_alpha_critical_exponent(concentrations_table_metadata, cleaned_data, critical_concentration, directory_to_store, out_id, debug_file)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation):  Error while calculating and preparing plot for alpha critical exponent calculation with exception {e}.")
            # Return error if any other part of the code fails
            return f"Error while calculating and preparing plot for alpha critical exponent calculation. Please, check you data and try again."
        
        # Get the phi critical exponent plot and calculations for phi (slope)
        try:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Generating plot for the phi critical exponent and calculating phi")
            phi_phi_critical_exponent_calculation, phi_standard_error_phi_critical_exponent_calculation, error_phi_critical_exponent_calculation = get_phi_critical_exponent(concentrations_table_metadata, cleaned_data, critical_concentration, ks, directory_to_store, out_id, debug_file)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation):  Error while calculating and preparing plot for phi critical exponent calculation with exception {e}.")
            # Return error if any other part of the code fails
            return f"Error while calculating and preparing plot for phi critical exponent calculation. Please, check you data and try again."

        if error_alpha_critical_exponent_calculation and error_phi_critical_exponent_calculation:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error from both alpha and phi calculations - {error_alpha_critical_exponent_calculation} and {error_phi_critical_exponent_calculation}")
            return f"{error_alpha_critical_exponent_calculation} {error_phi_critical_exponent_calculation}"
        elif error_alpha_critical_exponent_calculation:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error from alpha calculation - {error_alpha_critical_exponent_calculation}")
            return error_alpha_critical_exponent_calculation
        elif error_phi_critical_exponent_calculation:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error from phi calculation - {error_phi_critical_exponent_calculation}")
            return error_phi_critical_exponent_calculation

        try:
            rounded_m, rounded_m_std = round_mean_and_error(m_alpha_critical_exponent_calculation, m_std_alpha_critical_exponent_calculation)

            rounded_phi, rounded_phi_standard_error = round_mean_and_error(phi_phi_critical_exponent_calculation, phi_standard_error_phi_critical_exponent_calculation)
            # Make sure displayed decimal points are in line with the significant figures of std
            if rounded_phi_standard_error == int(rounded_phi_standard_error):
                phi_critical_exponent_expression = str(int(rounded_phi)) + ' ± ' + str(int(rounded_phi_standard_error))
            else: 
                phi_critical_exponent_expression = str(rounded_phi) + ' ± ' + str(rounded_phi_standard_error)

            alpha = 1 - rounded_m/rounded_phi
            # Calculate error via error propagation
            alpha_error = np.sqrt((rounded_m_std / rounded_phi)**2 +((rounded_m * rounded_phi_standard_error) / rounded_phi**2)**2)
            rounded_alpha, rounded_alpha_error = round_mean_and_error(alpha, alpha_error)
            # Make sure displayed decimal points are in line with the significant figures of std
            if rounded_alpha_error == int(rounded_alpha_error):
                alpha_critical_exponent_expression = str(int(rounded_alpha)) + ' ± ' + str(int(rounded_alpha_error))
            else: 
                alpha_critical_exponent_expression = str(rounded_alpha) + ' ± ' + str(rounded_alpha_error)

        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error preparing crititac exponents with exception - {e}")
            return "Error in final critical exponents calculation. Please check your data and selections and try again."

        try:
            data_fitness_report_critical_exponents = {
                "phi": rounded_phi,
                "phi_standard_error": rounded_phi_standard_error,
                "phi_critical_exponent_expression": phi_critical_exponent_expression,
                "alpha": rounded_alpha,
                "alpha_error": rounded_alpha_error,
                "alpha_critical_exponent_expression": alpha_critical_exponent_expression,
            }

            with open(f"{directory_to_store}/{out_id}_data_fitness_report_critical_exponents.json", "w") as json_file:
                json.dump(data_fitness_report_critical_exponents, json_file, indent=2)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error saving critical exponents - {e}")
            return "Error saving critical exponents. Please check your data and selections and try again."
    
        try:
            # Perform sanity checks and save warnings
            sanity_check_warnings = run_data_sanity_check(data, cleaned_data, concentrations_table_metadata)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation):  Error while running sanity checks with exception {e}.")
            # Return error if any other part of the code fails
            return f"Error while running sanity checks. Please, check you data and try again."

        try:
            # Write the concentrations usage into file. Do this here after error checks to save up space in case the file is not used
            # in the fronted due to errors.
            with open(f"{directory_to_store}/{out_id}_sanity_check_warnings.json", "w") as json_file:
                json.dump(sanity_check_warnings, json_file)
        except Exception as e:
            if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation):  Error while saving the sanity checks with exception {e}.")
            # Return error if any other part of the code fails
            return f"Error while saving the sanity checks. Please, check you data and try again."
        
        return ""
    
    except Exception as e:
        if debug_file:
                log_message(debug_file, f"DEBUG (run_calculation): Error while performing data report checks. Please, check you data and try again.")
        # Return error if any other part of the code fails
        return f"Error while performing data report checks. Please, check you data and try again."


# Create the argument parser
parser = argparse.ArgumentParser(description='Description of your program.')
parser.add_argument('--data_path', type=str, help='Path to the data table')
parser.add_argument('--directory_to_store', type=str, help='Path to the directory to store')
parser.add_argument('--out_id', type=str, help='ID for the files to uniqely identify them')
parser.add_argument('--critical_concentration', type=float, help='Critical concentration for collapses')
parser.add_argument('--ks', type=str, nargs='?', default='', help='String of k values separated by commas. If not specified, automatic k selection is made.')
parser.add_argument('--concentrations_to_omit', type=str, nargs='?', default='', help='String of concentrations separated by commas. If not specified, all concentrations are used.')
parser.add_argument('--debug', action='store_true', help='Enable debug mode')

args = parser.parse_args()
data_path = args.data_path
directory_to_store = args.directory_to_store
out_id = args.out_id
ks_str = args.ks
critical_concentration = args.critical_concentration
concentrations_to_omit_str = args.concentrations_to_omit
debug = args.debug

ks = np.array(ks_str.split(','), dtype=float)

if concentrations_to_omit_str:
    concentrations_to_omit = np.array(concentrations_to_omit_str.split(','), dtype=float)
else:
    concentrations_to_omit = np.array([])

if debug:
    debug_file=f"{directory_to_store}/{out_id}_debug_get_data_fitness_report.log"
else: 
    debug_file=""

result = run_calculation(data_path, directory_to_store, out_id, critical_concentration, ks, concentrations_to_omit, debug_file)
print(result)
with open(f"{directory_to_store}/{out_id}_get_data_fitness_report_error.txt", "w") as file:
    # Write the string to the file
    file.write(result)