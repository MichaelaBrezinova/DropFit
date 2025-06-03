<?php

global $chr_to_remove_from_filenames; // if you want a global array in PhP you need to declare it like this and then inside each funciton you either rewrite "global $chr_to_remove_from_filenames;" or you call it as $GLOBALS["chr_to_remove_from_filenames"]
$chr_to_remove_from_filenames= array( "(",")","[","]",">","{","}","<","/","\\","*","?","\"", "+", ",", ";", ":", ".", "|","\t","\r", "\n", " ");


// // DEBUGGING: Enable error reporting
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

require("vendruscolo_server_lib.php");
require('coh_server_lib.php');//contains function check_user

check_user(0, $check_academic=0 ); // first variable 0/1 is debug, it echo to screen the User ID and whether it's academic

$user=new User();
$username=$user->getUserName();
$user_id=$user->getUserID();

// Used for python scripts (scripts producing data and plots in the backend) debugging. 
// Only admin can see the log files produced by the debug feature.
$debug=0;
if($username=='admin') $debug=1;

//create a uniq id for the run        
$num=mt_rand();
$out_id=escapeshellcmd(str_replace($chr_to_remove_from_filenames, "_", $username)) . "_$num";

// Ensure JSON response
header('Content-Type: application/json'); 
$response = [];

# Load file and check if it is safe/ if we can carry on
$file_set_name='dropfit_uploaded_files'; // a set needs to be created with this name in concrete5

list($carry_on, $loaded_file_object, $input_file) = load_file($debug, $file_set_name);

if ($carry_on == 0) {
    // Return generic error if the try did not work. 
    $response = [
        "error" => "There seems to be an issue with processing the file. Check your file and try again. "
    ];

    // Return the response
    echo json_encode($response);
    exit;
}

try {

    // Prepare paths to the python scripts and writeable directory where resutls will be stored
    $cwd = getcwd();
    $dropfit_path = 'application/single_pages/dropfit/'; 
    $dropfit_absolute_path = $cwd.'/'.$dropfit_path;

    $absolute_writable_folder_path = $dropfit_absolute_path . 'data';

    $get_data_fitness_report_script_path = "python_scripts/get_data_fitness_report.py";
    $absolute_get_data_fitness_report_script_path= $dropfit_absolute_path . $get_data_fitness_report_script_path; 

    $temp_file_path = $input_file;

    if(!empty($_POST['kToConsider'])){
        $ks_append = " --ks "  . escapeshellarg($_POST['kToConsider']); 
    } else {
        $response = array(
            "error" => "There is an error with the k values. Please check your data and try again."
        );
        echo json_encode($response);
        exit;
    }

    if(!empty($_POST['concentrationsToOmit'])){
        $concentrations_to_omit = array_keys(array_filter($_POST['concentrationsToOmit'], function($value) {
            return $value === true;
        }));
        $concentrations_to_omit_string = implode(',', $concentrations_to_omit);
        $concentrations_to_omit_append = " --concentrations_to_omit " . escapeshellarg($concentrations_to_omit_string) ;
    } else {
        $concentrations_to_omit_append = " ";
    }

    # Execute the script to get the data fitness report
    if($debug==1){
        $output_data_fitness_report = exec("python3 " . escapeshellarg($absolute_get_data_fitness_report_script_path) . " --debug --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $ks_append . $concentrations_to_omit_append . " --critical_concentration " . escapeshellarg($_POST['criticalConcentration']));
    } else{
        $output_data_fitness_report = exec("python3 " . escapeshellarg($absolute_get_data_fitness_report_script_path) . " --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $ks_append . $concentrations_to_omit_append . " --critical_concentration " . escapeshellarg($_POST['criticalConcentration']));
    }
    
    $sanity_check_warnings_file_path = $absolute_writable_folder_path . '/' . $out_id . '_sanity_check_warnings.json';
    $kdes_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_kdes_plot.png";
    $concentrations_collapse_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_collapse_concentration_plot.png";
    $alpha_critical_exponent_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_alpha_critical_exponent_plot.png";
    $phi_critical_exponent_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_phi_critical_exponent_plot.png";
    $data_fitness_report_critical_exponents_file_path = $absolute_writable_folder_path . '/' . $out_id . '_data_fitness_report_critical_exponents.json';

    if( $output_data_fitness_report === "" && file_exists($sanity_check_warnings_file_path)
                                && file_exists($kdes_plot_file_path)
                                && file_exists($concentrations_collapse_plot_file_path)
                                && file_exists($alpha_critical_exponent_plot_file_path)
                                && file_exists($phi_critical_exponent_plot_file_path)
                                && file_exists($data_fitness_report_critical_exponents_file_path)){
        // Read the contents of the file into a string
        $sanity_check_warnings_raw = file_get_contents($sanity_check_warnings_file_path);
        $sanity_check_warnings = json_decode($sanity_check_warnings_raw, true);

        $kdes_plot = file_get_contents($kdes_plot_file_path);
        $concentrations_collapse_plot = file_get_contents($concentrations_collapse_plot_file_path);

        $alpha_critical_exponent_plot = file_get_contents($alpha_critical_exponent_plot_file_path);
        $phi_critical_exponent_plot = file_get_contents($phi_critical_exponent_plot_file_path);

        $data_fitness_report_critical_exponents_raw = file_get_contents($data_fitness_report_critical_exponents_file_path);
        $data_fitness_report_critical_exponents = json_decode($data_fitness_report_critical_exponents_raw, true);

        if ($sanity_check_warnings === false || empty($sanity_check_warnings 
                || empty($kde_plot) || empty($concentrations_collapse_plot)) 
                || empty($alpha_critical_exponent_plot)
                || empty($phi_critical_exponent_plot) 
                || $data_fitness_report_critical_exponents === false || empty($data_fitness_report_critical_exponents)) {
            $response = array(
                "error" => "Unknown error happened. Check your file and try again."
            );
        } else {
            $response = array(
                "sanity_check_warnings" => $sanity_check_warnings,
                "kdes_plot" => base64_encode($kdes_plot),
                "concentrations_collapse_plot" => base64_encode($concentrations_collapse_plot),
                "alpha_critical_exponent_plot" => base64_encode($alpha_critical_exponent_plot),
                "phi_critical_exponent_plot" => base64_encode($phi_critical_exponent_plot),
                "critical_exponents" => $data_fitness_report_critical_exponents,
            );
        }
    } elseif ($output_data_fitness_report!= '') {
        $response = array(
            "error" => $output_data_fitness_report
        );
    }  else {
        $response = array(
            "error" => "Unknown error happened. Check your data and selections and try again. 140"
        );
    }

} catch (Exception $e) {
    // Return generic error if the try did not work. 
    $response = [
        "error" => "Unknown error happened. Check your file and try again."
    ];
}

echo json_encode($response);
exit;
?>

