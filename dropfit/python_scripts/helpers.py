import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
from scipy.stats import linregress, gaussian_kde
import math

# This function loggs into a debug file.
def log_message(debug_file, message):
    if debug_file and message:
        with open(debug_file, "a") as file:
            file.write(message + "\n")

# This function calculates approximation of PDF from the histogram fitten on the data. It returns centers of the bin with the 
# corresponding density values.
def get_histogram_data(x, bins=30):
    cleaned_x = x[~np.isnan(x)]

    hist, bins = np.histogram(cleaned_x,  bins=bins, density=True)
    bin_centers = (bins[:-1] + bins[1:]) / 2
    return bin_centers, hist

# This function calculates PDF from the data and then uses it to produce data for the PDF curve. It also calculates PDF value for the original
# data so they can be plotted. 
def get_kde(x):
    cleaned_x = x[~np.isnan(x)]
    kde = gaussian_kde(cleaned_x)
    x_values = np.linspace(cleaned_x.min(), cleaned_x.max(), 1000)
    y_values = kde(x_values)

    return x_values, y_values, cleaned_x, kde(cleaned_x)

# This function is a reference function of normal distribution with mean 0 and std 1. 
def expFunc(x):
        return np.exp(-(x**2)/2)/np.sqrt(2*np.pi)

# This function calculates the collapse curve. It either uses kde or histogram to approximate the PDF from the data.
def get_collapsed_curve_data(original_s, debug_file="", use_kde= True, bins=30):
    # Clean the data
    original_s = original_s[~np.isnan(original_s)] # Filter out NaNs
    original_s = original_s[original_s>0] # Filter out negatives
    original_pdf_s = None

    # Calculate variables required for the plot
    ln_s_0 = np.nanmean(np.log(original_s))
    sigma = np.nanstd(np.log(original_s))

    if debug_file:
        log_message(debug_file, f"DEBUG (helpers:get_collapsed_curve_data): ln_s_0 is {ln_s_0} and sigma is {sigma}.")

    if use_kde:
        plot_s ,plot_pdf_s, original_s, original_pdf_s = get_kde(original_s)
    else:
        plot_s ,plot_pdf_s = get_histogram_data(original_s, bins=bins)

    # Calculate the data for plot
    y = plot_pdf_s * plot_s * sigma
    x = (np.log(plot_s) - ln_s_0) / sigma

    if use_kde:
        # With KDE used for PDF. We are able to calculate PDF values also for original data.
        # Hence calculate plot values also with these.
        original_data_y = original_pdf_s * original_s * sigma
        original_data_x = (np.log(original_s) - ln_s_0) / sigma

        return x, y, original_data_x, original_data_y
    else: 
        return x,y 

# Currently not used. This function uses the critical concentration value to calculate the collapse. 
def get_critical_concentration_collapsed_curve_data(original_s, concentration, critical_concentration, use_kde=True, bins=20):
    original_s = original_s[~np.isnan(original_s)] # Filter out NaNs
    original_s = original_s[original_s>0] # Filter out negatives

    if use_kde:
        plot_s ,plot_pdf_s, _, _ = get_kde(original_s)
    else:
        plot_s ,plot_pdf_s = get_histogram_data(original_s, bins=bins)

    x = plot_s*(1 - concentration/critical_concentration)
    y = plot_pdf_s/(1 - concentration/critical_concentration)

    return x, y

# Currently not used. This function calculates the lower and upper percentile values, using ratio to cover
def get_x_min_and_max(df, ratio_to_cover=0.9):
    lower_percentile = (1 - ratio_to_cover)/2
    higher_percentile = 1 - lower_percentile

    values = df.values.flatten()
    values = values[~np.isnan(values)]

    # Calculate the percentiles
    lower_percentile_value = np.percentile(values, lower_percentile*100)
    upper_percentile_value = np.percentile(values, higher_percentile*100)

    return lower_percentile_value, upper_percentile_value

# This function goes through the data and returns:
# 1. selected_valid_columns_and_indices - columns that will be used for calculation - these are valid columns, with concentration NOT specified in the omit list, 
#    the format is dictionary {'concentration_value': [indices_of_columns_with_replicates_of_this_concentration],...}
# 2. all_valid_concentrations_usage  - the usage of concentrations, the format is dictionary {'concentration_value' : True/False, ...}
def get_valid_column_indexes(data, concentrations_to_omit =  np.array([])):
    # name - key, list of indexes - value
    selected_valid_columns_and_indices = {}
    all_valid_concentrations_usage = {}
    for index,column in enumerate(data.columns):
        # Ignore columns with an unsupecified header, ie. concentration
        if not column.startswith('Unnamed'):
            # This represents the concentration. We are querying the string before '.' because if the concentrations have replicates, the columns will have
            # suffixes to differentiate them 
            column_prefix = float(column.split('.')[0]) 

            # Add column prefix (concentration) to the map of all valid concentrations. Indicate that is is not used yet (False value).
            if column_prefix not in all_valid_concentrations_usage.keys():
                all_valid_concentrations_usage[column_prefix] = False

            if len(concentrations_to_omit) == 0 or not np.isin(column_prefix, concentrations_to_omit):
                if column_prefix not in selected_valid_columns_and_indices.keys():
                    selected_valid_columns_and_indices[column_prefix] = [index]
                    # Mark that the concentration (represented by column prefix)is now being used.
                    all_valid_concentrations_usage[column_prefix] = True
                else:
                    selected_valid_columns_and_indices[column_prefix].append(index)
    
    # Sort concentrations from smallest to largest
    selected_valid_columns_and_indices = dict(sorted(selected_valid_columns_and_indices.items(), key=lambda x: int(x[0])))
    all_valid_concentrations_usage = dict(sorted(all_valid_concentrations_usage.items(), key=lambda x: int(x[0])))
     
    return selected_valid_columns_and_indices, all_valid_concentrations_usage

# Currently not used. This function calculates the mean and std for a given column data.
def get_column_mean_and_std(column):
    # Filter out non-numeric and NaN values
    values = column.dropna().astype(float)  # Convert to float to handle non-numeric values
    values = values[values > 0]  # Filter out negatives if needed
    
    # Check if there are any values left after filtering
    if len(values) > 0:
        return np.mean(values), np.std(values)
    else:
        return np.nan, np.nan

# This function calculates the columns kth moment, as mean of [values of the column to the power of k]
def get_column_kth_moment(column, k):
    # Filter out non-numeric and NaN values
    values = column.dropna().astype(float)  # Convert to float to handle non-numeric values
    values = values[values > 0]  # Filter out negatives if needed
    
    # Check if there are any values left after filtering
    if len(values) > 0:
        # In case of cumulative distribution, kth moment is E[x^k] the mean of the kth power of the values
        return np.mean(values**k)
    else:
        return np.nan

# This function describes a linear function.    
def linear_function(x, slope, interept):
    return slope * x + interept

# This function rounds mean and std, taking into account significant figures of the std. 
def round_mean_and_std(mean,std):
    if std == 0:
        return mean, std
    else:
        significant_figures = -int(math.floor(math.log10(abs(std))))
        round_std  = round(std, significant_figures)
        round_mean = round(mean,significant_figures)
        return round_mean, round_std

# This function removes empty columns from the dataframe. 
def remove_empty_columns(data):
    # Drop columns with all NaN values
    data.dropna(axis=1, how='all', inplace=True)
    
    # Check for columns with only headers but no data
    empty_columns = []
    for column in data.columns:
        if data[column].count() == 0:
            empty_columns.append(column)
    
    # Drop columns with only headers but no data
    data.drop(empty_columns, axis=1, inplace=True)
    return data
