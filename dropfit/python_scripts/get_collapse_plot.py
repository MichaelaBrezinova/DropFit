import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
from scipy.stats import linregress
from scipy.optimize import curve_fit
from scipy.stats import gaussian_kde
from helpers import get_valid_column_indexes, get_collapsed_curve_data, expFunc, remove_empty_columns, log_message, is_positive_number, rename_duplicate_columns
import argparse

def get_collapse_plot(concentrations_table_metadata, cleaned_data, directory_to_store, out_id,  debug_file="", use_kde=True):
    fig, ax = plt.subplots(figsize=(10, 8))

    min_x = float('inf')     # positive infinity
    max_x = float('-inf')    # negative infinity

    if use_kde:
            # Get bandwith for KDE from all data so then it is consistent across concentrations
            all_data = cleaned_data.values.flatten()
            # Remove NaNs and non-positive values
            pooled_data = all_data[~np.isnan(all_data)]
            pooled_data = pooled_data[pooled_data > 0]
            global_bandwidth = gaussian_kde(pooled_data).scotts_factor()

    # For each concentration calculate the collapse curve
    for concentration, column_indices in concentrations_table_metadata.items():
        # Get relevant columns (the concentration replicas)
        concentration_relevant_columns = cleaned_data.iloc[:, column_indices]
        # Flatten and join all the replica values together
        concentration_data = np.array(concentration_relevant_columns.values.flatten().tolist())
        
        # Remove NAN values and 0s
        concentration_data = concentration_data[~np.isnan(concentration_data)]
        concentration_data = concentration_data[concentration_data >0]

        # Calculate the collapse either using KDE or histogram.
        if use_kde:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_collapse_plot): Getting collapse curve data for concentration {concentration}. Using KDE.")
            
            x, y, original_x, original_y = get_collapsed_curve_data(concentration_data, debug_file, use_kde, global_bandwidth=global_bandwidth)
            plt.plot(x, y, label=concentration)
            
            # With KDE used for PDF, we were able to calculate the plot values also for the original data. Display these as
            # scatter plot.
            plt.scatter(original_x, original_y, s=5)

            # Update x-axis limits
            if min(x)<min_x:
                min_x=min(x)
            if max(x)>max_x:
                max_x=max(x)
        else:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_collapse_plot): Getting collapse curve data for concentration {concentration}. Using histogram.")
            
            x, y, _, _ = get_collapsed_curve_data(concentration_data, debug_file, use_kde)
            plt.plot(x, y, label=concentration)

            # Update x-axis limits
            if min(x)<min_x:
                min_x=min(x)
            if max(x)>max_x:
                max_x=max(x)
        

    axis_padding=abs(max_x-min_x)/5
    x = np.linspace(min_x-axis_padding, max_x+axis_padding, 1000)
    plt.plot(x, expFunc(x), color='gray',
                label='reference', linestyle='--')

    plt.title('Collapse of droplet size distributions', fontsize=22)
    plt.xlabel(r'$(\ln(s) - \ln(s_0))/\sigma$', fontsize=20)
    plt.ylabel(r'$P(s) \times s \times \sigma$', fontsize=20)
    plt.legend(loc='upper right', fontsize=16)
    plt.xlim(min_x-axis_padding,max_x+axis_padding)
    plt.savefig(f"{directory_to_store}/{out_id}_collapse_plot.png", dpi=300, bbox_inches='tight')
    if debug_file:
                log_message(debug_file, f"DEBUG (get_collapse_plot): Saved collapse plot.")
    return

def run_calculation(data_path, directory_to_store, out_id, concentrations_to_omit = np.array([]), debug_file=""):
# def run_calculation(data_path, directory_to_store, out_id, concentrations_to_omit = np.array([])):

    try:
        # Try to load the CSV file
        data = pd.read_csv(data_path)
    except Exception as e:
        # Return error if loading the CSV file fails
        return f"Error loading the CSV file. Please, check the format of your file."
    
    if debug_file:
        log_message(debug_file, f"DEBUG (run_calculation): csv file loaded, with {str(len(data))} rows")

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
        concentrations_table_metadata, _  = get_valid_column_indexes(data, concentrations_to_omit)
        if debug_file:
            log_message(debug_file, f"DEBUG (run_calculation): concentration table metadata: {str(concentrations_table_metadata)}")

        get_collapse_plot(concentrations_table_metadata, data, directory_to_store, out_id, debug_file, use_kde=True)
    except Exception as e:
        # Return error if loading the CSV file fails
        return f"Error while performing collapse calculation. Check you file and try again."
    
    return ""

# Create the argument parser
parser = argparse.ArgumentParser(description='Description of your program.')
parser.add_argument('--data_path', type=str, help='Path to the data table')
parser.add_argument('--directory_to_store', type=str, help='Path to the directory to store')
parser.add_argument('--out_id', type=str, help='ID for the files to uniqely identify them')
parser.add_argument('--concentrations_to_omit', type=str, nargs='?', default='', help='String of concentrations separated by commas. If not specified, all concentrations are used.')
parser.add_argument('--debug', action='store_true', help='Enable debug mode')

args = parser.parse_args()
data_path = args.data_path
directory_to_store = args.directory_to_store
out_id = args.out_id
concentrations_to_omit_str = args.concentrations_to_omit
debug = args.debug

if concentrations_to_omit_str:
    concentrations_to_omit = np.array(concentrations_to_omit_str.split(','), dtype=float)
else:
    concentrations_to_omit = np.array([])

if debug:
    debug_file=f"{directory_to_store}/{out_id}_debug_get_collapse_plot.log"
else:
    debug_file=""

result = run_calculation(data_path, directory_to_store, out_id, concentrations_to_omit, debug_file)

print(result)