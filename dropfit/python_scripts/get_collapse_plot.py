import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
from scipy.stats import linregress
from scipy.optimize import curve_fit
from helpers import get_valid_column_indexes, get_collapsed_curve_data, expFunc, remove_empty_columns, log_message
import argparse

def get_collapse_plot(concentrations_table_metadata, cleaned_data, directory_to_store, out_id,  debug_file="", use_kde=True):
    fig, ax = plt.subplots(figsize=(10, 8))

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
            
            x, y, original_x, original_y = get_collapsed_curve_data(concentration_data, debug_file, use_kde)
            plt.plot(x, y, label=concentration)
            
            # With KDE used for PDF, we were able to calculate the plot values also for the original data. Display these as
            # scatter plot.
            plt.scatter(original_x, original_y, s=5)
        else:
            if debug_file:
                log_message(debug_file, f"DEBUG (get_collapse_plot): Getting collapse curve data for concentration {concentration}. Using histogram.")
            
            x, y = get_collapsed_curve_data(concentration_data, debug_file, use_kde)
            plt.plot(x, y, label=concentration)
        

    # Add reference nomal distribution
    x = np.linspace(-2, 2, 1000)
    plt.plot(x, expFunc(x), color='gray',
                label='reference', linestyle='--')

    plt.title('Collapse of droplet size distributions', fontsize=22)
    plt.xlabel(r'$(\ln(s) - \ln(s_0))/\sigma$', fontsize=20)
    plt.ylabel(r'$P(s) \times s \times \sigma$', fontsize=20)
    plt.legend(loc='upper right', fontsize=16)
    plt.xlim(-4,4)
    plt.savefig(f"{directory_to_store}/{out_id}_collapse_plot.png", dpi=300, bbox_inches='tight')
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
        
    data = remove_empty_columns(data)
    if data.empty:
        return f"Error with the CSV file. There are no valid concentration data. Please, check if your file has the right structure and contains right values."
    
    try:
        if debug_file:
            log_message(debug_file, "DEBUG (run_calculation): Starting run_calculation core eval")
        # cleaned_data = data.iloc[1:,:].astype(float)
        cleaned_data = data.astype(float)

        # Get valid column indexes for corresponding concentrations from the loaded data. 
        concentrations_table_metadata, _  = get_valid_column_indexes(data, concentrations_to_omit)
        if debug_file:
            log_message(debug_file, f"DEBUG (run_calculation): concentration table metadata: {str(concentrations_table_metadata)}")

        get_collapse_plot(concentrations_table_metadata, cleaned_data, directory_to_store, out_id, debug_file, use_kde=True)
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
