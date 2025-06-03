import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
from scipy.stats import linregress, gaussian_kde
from scipy.optimize import curve_fit
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
def get_kde(x, bw_method):
    cleaned_x = x[~np.isnan(x)]
    cleaned_x  = cleaned_x[cleaned_x>0]
    kde = gaussian_kde(cleaned_x, bw_method= bw_method)
    x_values = np.linspace(cleaned_x.min(), cleaned_x.max(), 1000)
    y_values = kde(x_values)

    return x_values, y_values, cleaned_x, kde(cleaned_x)

# This function is a reference function of normal distribution with mean 0 and std 1. 
def expFunc(x):
        return np.exp(-(x**2)/2)/np.sqrt(2*np.pi)

# This function calculates the collapse curve. It either uses kde or histogram to approximate the PDF from the data.
def get_collapsed_curve_data(original_s, debug_file="", use_kde= True, global_bandwidth=0, bins=30):
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
        plot_s ,plot_pdf_s, original_s, original_pdf_s = get_kde(original_s, bw_method=global_bandwidth)
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
def round_mean_and_error(mean,std):
    if std == 0:
        return round(mean, 2), std
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

def do_similarity_check(data_to_check, max_cv=0.2):
    """Returns True if coefficient of variation is below threshold."""
    mean = data_to_check.mean()
    std = data_to_check.std()
    if mean == 0:
        return False  # Avoid divide-by-zero
    cv = std / mean
    return cv <= max_cv

def run_data_sanity_check(data, cleaned_data, concentrations_table_metadata):
    potential_warnings = []
    
    # Convert all values to numeric, coercing errors to NaN
    data_without_nonnumeric = data.apply(pd.to_numeric, errors='coerce')

    # Original NaN mask
    original_nan_mask = data.isna()
    # New NaN mask after conversion
    data_without_nonnumeric_nan_mask = data_without_nonnumeric.isna()
    # Non-numeric values: NaNs introduced by coercion, ignoring original NaNs
    non_numeric_mask = data_without_nonnumeric_nan_mask & ~original_nan_mask
    # Check if any non-numeric values exist
    has_non_numeric = non_numeric_mask.any().any()
    has_values_le_zero = (data_without_nonnumeric_nan_mask <= 0).any().any()

    if has_non_numeric:
        potential_warnings.append('Data contained non-numeric values that have been removed (could have resulted in removal of some concentrations)')
    if has_values_le_zero:
        potential_warnings.append('Data contained zero or negative values that have been removed (could have resulted in removal of some concentrations)')
    
    
    means_across_concentrations = []
    counts_across_concentrations = []
    numbers_of_replicates_across_concentrations = []

    concentrations_with_means_variation = []
    concentrations_with_counts_variation = []
    concentrations_with_low_datapoints = []

    for key in concentrations_table_metadata.keys():
        concentration_data = cleaned_data.iloc[:, concentrations_table_metadata[key]]
        concentration_data = concentration_data[concentration_data>0]
        replicate_means = concentration_data.mean(skipna=True)
        replicate_counts = concentration_data.count()

        are_means_similar = do_similarity_check(replicate_means, max_cv=0.2)
        are_counts_similar = do_similarity_check(replicate_counts, max_cv=0.2)

        if not are_means_similar:
            concentrations_with_means_variation.append(key)
        if not are_counts_similar:
            concentrations_with_counts_variation.append(key)

        if (replicate_counts < 50).any():
            concentrations_with_low_datapoints.append(key)

        means_across_concentrations.append(replicate_means.mean())
        counts_across_concentrations.append(replicate_counts.mean())
        numbers_of_replicates_across_concentrations.append(len(concentrations_table_metadata[key]))

    if concentrations_with_means_variation:
        potential_warnings.append(
            "There is variation in mean droplet size across replicates for concentrations: " + 
            ", ".join(map(str, concentrations_with_means_variation))
        )
    if concentrations_with_counts_variation:
        potential_warnings.append(
            "There is variation in number of datapoints across replicates for concentrations: " + 
            ", ".join(map(str, concentrations_with_counts_variation))
        )
    if concentrations_with_low_datapoints:
        potential_warnings.append(
            "There is less than 50 datapoints for a replicate/replicates of concentrations: " +  
            ", ".join(map(str, concentrations_with_low_datapoints))
        )

    are_means_across_concentrations_similar = do_similarity_check(np.array(means_across_concentrations), max_cv=0.3)
    are_counts_across_concentrations_similar = do_similarity_check(np.array(counts_across_concentrations), max_cv=0.3)
    are_numbers_of_replicates_across_concentrations_similar = do_similarity_check(np.array(numbers_of_replicates_across_concentrations), max_cv=0.3)

    if not are_means_across_concentrations_similar:
        potential_warnings.append("There is variation in mean droplet size across concentrations")
    if not are_counts_across_concentrations_similar:
        potential_warnings.append("There is variation in number of datapoints across concentrations")
    if not are_numbers_of_replicates_across_concentrations_similar:
        potential_warnings.append("There is variation in number of replicates across concentrations")

    return potential_warnings


def get_phi_exponent_calculation_data(concentrations_table_metadata, data, critical_concentration, k_to_use):

    plot_data = {}

    for k in k_to_use:
        # Prepare dictionary of values for the given k
        replicates_per_concentration = []

        plot_data[k] = {'x': [], 'y': [], 'y_std': []}

        # Calculate kth moment for every concentration's replicate (all columns) (independently)
        kth_moments = list(data.apply(lambda column: get_column_kth_moment(column, k=k), axis=0))
        # Calculate (k+1)th moment for every concentration's replicate (all columns) (independently)
        k_plus_1th_moments = list(data.apply(lambda column: get_column_kth_moment(column, k=k+1), axis=0))
        # Calculate ratio of the moments, ie <s_(k+1)>/<s_k>
        
        moment_ratios = [s_k_plus_1 / s_k if k != 0 else float('inf') for s_k_plus_1, s_k in zip(k_plus_1th_moments, kth_moments)]
        if np.any(np.isinf(moment_ratios)):
            return {}, f"There was an error with calculating {k}th moment, most likely it is 0 which makes calculation of moment ratios impossible."

        for concentration, concentration_indexes in concentrations_table_metadata.items():
            replicates_per_concentration.append(len(concentration_indexes))

            # Extract relevant columns (replicates) for this concentration
            relevant_column_moment_ratios_logged = [np.log(moment_ratios[index]) for index in concentration_indexes]

            # Calculate the mean and std of the moment ratios from the replicates
            mean_of_relevant_column_moment_ratios_logged = np.mean(relevant_column_moment_ratios_logged)
            std_of_relevant_column_moment_ratios_logged = np.std(relevant_column_moment_ratios_logged)

            # Prepare the plot data
            plot_data[k]['y'].append(mean_of_relevant_column_moment_ratios_logged)
            plot_data[k]['y_std'].append(std_of_relevant_column_moment_ratios_logged)

            # x should be -ln(1-concentration/critical_concentration)
            plot_data[k]['x'].append(-np.log(1-concentration/critical_concentration))

        # For cases when there is only 1 replicate per concentration, y_std will be 0. This will give too much weight
        # to this concentration which is not ideal. Correct this by calculating an approximate y_std calculated from 
        # y_stds of other concentrations that have more replicates. Using scaling of the concentrations' means to get
        # values appropriate for the given concentration. 
        replicates_per_concentration = np.array(replicates_per_concentration)
        y_std = np.array(plot_data[k]['y_std'])
        indices_to_replace = (y_std == 0) & (replicates_per_concentration == 1)
        if len(y_std[~indices_to_replace])!=0:
            replacement_scaler = np.mean(y_std[~indices_to_replace]/np.array(plot_data[k]['y'])[~indices_to_replace])
            y_std[indices_to_replace] = replacement_scaler * np.array(plot_data[k]['y'])[indices_to_replace]
        # in case there are no concentrations with more replicates, set the y_st to 0.001 (small value)
        else:
            replacement_value = 0.001
            y_std[indices_to_replace] = replacement_value
        
        plot_data[k]['y_std'] = y_std.tolist()

        # Calculate linear regression fit on the datapoints, using y_stds to provide weight for the linear regression. 
        popt, pcov = curve_fit(linear_function, plot_data[k]['x'], plot_data[k]['y'])
        # popt, pcov = curve_fit(linear_function, plot_data[k]['x'], plot_data[k]['y'], sigma=plot_data[k]['y_std'])
        slope_error, _ = np.sqrt(np.diag(pcov)) 
        slope, intercept = popt

        # Calculating the slope error
        # Handling of only 2 concentrations resulting in slope errors 0 due to
        # perfect linear fit. This, however, should not change result as for different ks, this the slope_error be 0 instead of inf, 
        # and they will be all the same so nothing changes in selection of ks to consider.
        if np.any(np.isinf(pcov)) and len(concentrations_table_metadata)==2:
            slope_error = 0
        # Handling of any other error with calculating pcov matrix.
        elif np.any(np.isinf(pcov)):
            return {}, "Error with calculating slope errors for alpha critical exponent fit for an unknown reason."
        # Retrieve slope errors in all other cases.
        else:
            slope_error, _ = np.sqrt(np.diag(pcov)) 
        
        # Calculate fit line range safely
        x_min, x_max = min(plot_data[k]['x']), max(plot_data[k]['x'])
        x_range = abs(x_max - x_min)
        plot_data[k]['slope'] = slope
        plot_data[k]['slope_error'] = slope_error
        plot_data[k]['line_fit_xs'] = np.linspace(x_min - x_range / 5, x_max + x_range / 5, 500)
        plot_data[k]['line_fit_ys'] = slope * plot_data[k]['line_fit_xs'] + intercept
    
    return plot_data, ""

def get_alpha_exponent_calculation_data(concentrations_table_metadata, data, critical_concentration):
    # Prepare output dictionary
    plot_data = {'y':[], 'y_std': [],'x':[]}

    # Get all column first moments (means)
    first_moments= list(data.apply(lambda column: get_column_kth_moment(column, k=1), axis=0))

    replicates_per_concentration = []

    # For each concentration, calculate datapoint (and its std)
    for concentration, concentration_indexes in concentrations_table_metadata.items():
                replicates_per_concentration.append(len(concentration_indexes))

                # Extract relevant columns and calculate their log (as y is ln<s>)
                relevant_column_first_moments_logged = [np.log(first_moments[index]) for index in concentration_indexes]

                # Calculate mean and std of first_moments_logged from replicates of the same concentration
                mean_of_relevant_column_first_moments = np.mean(relevant_column_first_moments_logged)
                std_of_relevant_column_first_moments = np.std(relevant_column_first_moments_logged)

                plot_data['y'].append(mean_of_relevant_column_first_moments)
                plot_data['y_std'].append(std_of_relevant_column_first_moments)

                # x should be -ln(1-concentration/critical_concentration)
                plot_data['x'].append(-np.log(1-concentration/critical_concentration))

    # For cases when there is only 1 replicate per concentration, y_std will be 0. This will give too much weight
    # to this concentration which is not ideal. Correct this by calculating an approximate y_std calculated from 
    # y_stds of other concentrations that have more replicates. Using scaling of the concentrations' means to get
    # values appropriate for the given concentration. 
    replicates_per_concentration = np.array(replicates_per_concentration)
    y_std = np.array(plot_data['y_std'])
    indices_to_replace = (y_std == 0) & (replicates_per_concentration == 1)
    if len(y_std[~indices_to_replace])!=0:
        replacement_scaler = np.mean(y_std[~indices_to_replace]/np.array(plot_data['y'])[~indices_to_replace])
        y_std[indices_to_replace] = replacement_scaler * np.array(plot_data['y'])[indices_to_replace]
    # in case there are no concentrations with more replicates, set the y_st to 0.001 (small value)
    else:
        replacement_value = 0.001
        y_std[indices_to_replace] = replacement_value

    plot_data['y_std'] = y_std.tolist()

    # Calculate linear fit from the datapoints, taking the standard deviations of points into account
    popt, pcov = curve_fit(linear_function, plot_data['x'], plot_data['y'], sigma=plot_data['y_std'])

    # Calculating the slope from this linear fit
    slope, intercept = popt

    # Calculating the slope error
    # Handling of only 2 concentrations resulting in slope errors 0 due to
    # perfect linear fit. This, however, should not change result as for different ks, this the slope_error be 0 instead of inf, 
    # and they will be all the same so nothing changes in selection of ks to consider.
    if np.any(np.isinf(pcov)) and len(concentrations_table_metadata)==2:
        slope_error = 0
    # Handling of any other error with calculating pcov matrix.
    elif np.any(np.isinf(pcov)):
        return {}, "Error with calculating slope errors for alpha critical exponent fit for an unknown reason."
    # Retrieve slope errors in all other cases.
    else:
        slope_error, _ = np.sqrt(np.diag(pcov)) 
    
    # Prepare data for plotting the linear fit
    min_x, max_x = min(plot_data['x']), max(plot_data['x'])

    # Adjusting x limits so there is some padding on both sides
    axis_padding=abs(max_x-min_x)/5
    x_fit = np.linspace(min_x- axis_padding, max_x + axis_padding, 500)
    y_fit = slope * x_fit + intercept

    plot_data['x_fit'] = x_fit
    plot_data['y_fit'] = y_fit
    plot_data['slope'] = slope
    plot_data['slope_error'] = slope_error
    plot_data['intercept'] = intercept

    return plot_data, ""