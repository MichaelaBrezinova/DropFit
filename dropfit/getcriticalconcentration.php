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

    $critical_concentration_and_plot_script = "python_scripts/get_critical_concentration_and_plot.py";
    $absolute_concentration_and_plot_script_path= $dropfit_absolute_path . $critical_concentration_and_plot_script; 

    $collapse_plot_script = "python_scripts/get_collapse_plot.py";
    $absolute_collapse_plot_script_path= $dropfit_absolute_path . $collapse_plot_script; 

    // Get path to the temp uploaded file
    // $temp_file_path = $_FILES["userfile"]["tmp_name"];
    $temp_file_path = $input_file;

    // Check if user predefined k values to use. If so, these will be passed to the python script
    if (!isset($_POST['autoKSelect'])){
        $input_k_values = [];

        // Loop through each input field and check if it's non-empty
        for ($i = 1; $i <= 4; $i++) {
            $input_k_name = 'k' . $i;
            if (!empty($_POST[$input_k_name])) {
                $input_k_values[] = $_POST[$input_k_name];
            }
        }

        $concatenated_k_values = implode(',', $input_k_values); 
        $manual_ks_append = " --manual_ks "  . escapeshellarg($concatenated_k_values);
    } else {
        $manual_ks_append = "";
    }

    // Check if user predefined which k values should be omitted. If so, these will be passed to the python script
    if(isset($_POST['concentrationsToOmit'])){
        $concentrations_to_omit_append = " --concentrations_to_omit " . escapeshellarg($_POST['concentrationsToOmit']) ;
    } else {
        $concentrations_to_omit_append = "";
    }

    // FOR DEBUGGING: Full raw script
    // $output_critical_concentration_and_plot = exec("python /var/www/html/application/single_pages/dropfit/python_scripts/get_critical_concentration_and_plot.py "  . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --data_path " . escapeshellarg($temp_file_path) . " --out_id "  . escapeshellarg($out_id) . $manual_ks_append );
    
    // Execute scripts to get value of and plot critical concentration as well as collapse plot
    // If $debug==1, we add debug tag to the python scripts which will produce debug log files.
    if($debug==1){
        $output_critical_concentration_and_plot = exec("python3 " . escapeshellarg($absolute_concentration_and_plot_script_path) . " --debug --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $manual_ks_append . $concentrations_to_omit_append);
        $output_collapse_plot = exec("python3 " . escapeshellarg($absolute_collapse_plot_script_path) . " --debug --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $concentrations_to_omit_append);    
    }
    else {
        $output_critical_concentration_and_plot = exec("python3 " . escapeshellarg($absolute_concentration_and_plot_script_path) . " --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $manual_ks_append . $concentrations_to_omit_append);
        $output_collapse_plot = exec("python3 " . escapeshellarg($absolute_collapse_plot_script_path) . " --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $concentrations_to_omit_append);
    }

     // Prepare paths to files that should be outputs of the scripts that were run - (get_critical_concentration_and_plot.py 
    // and get_collapse_plot.py)
    $results_and_metadata_for_frontend_path = $absolute_writable_folder_path . '/' . $out_id . '_results_and_metadata_for_frontend.json';
    $concentration_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_concentration_plot.png";
    $collapse_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_collapse_plot.png";


    // If all these files exist and there were no errors comming from the scripts
    // then get contents of these files and process them accordingly
    if(file_exists($results_and_metadata_for_frontend_path) && file_exists($concentration_plot_file_path)
                                    && $output_critical_concentration_and_plot === '' 
                                    && file_exists($collapse_plot_file_path)
                                    && $output_collapse_plot === "") {
        
                                        // Read the contents of the files into a string
        $results_and_metadata_for_frontend_raw = file_get_contents($results_and_metadata_for_frontend_path);
        $results_and_metadata_for_frontend = json_decode($results_and_metadata_for_frontend_raw, true);
        $concentration_plot = file_get_contents($concentration_plot_file_path);
        $collapse_plot = file_get_contents($collapse_plot_file_path);

        // If the files have corrupted content, send error message to the frontend.
        // If the contents of the files are okay, then send relevant information 
        // to the frontend.
        if ($results_and_metadata_for_frontend_raw === false || $concentration_plot===false 
            || $collapse_plot ===false || empty($results_and_metadata_for_frontend_raw) || empty($concentration_plot) 
            || empty($collapse_plot)) {
            $response = array(
                "error" => "Unknown error happened. Check your file and try again."
            );
        } else {
            $response = array(
                "concentration_plot" => base64_encode($concentration_plot), // send plot content directly 
                "collapse_plot" => base64_encode($collapse_plot), // send plot content directly
                "results_and_metadata_for_frontend" => $results_and_metadata_for_frontend
            );
        }
    
    // If the critical_concentration_and_plot returned error but the collapse_plot did not,
    // send the critical_concentration_and_plot error to the frontend.
    } elseif ($output_critical_concentration_and_plot !== '' && $output_collapse_plot == '') {
        $response = array(
            "error" => $output_critical_concentration_and_plot
        );
    // If the collapse_plot returned error but the critical_concentration_and_plot did not,
    // send the collapse_plot error to the frontend.
    } elseif ($output_collapse_plot != '' && $output_critical_concentration_and_plot == '' ) {
        $response = array(
            "error" => $output_collapse_plot
        );
    // If both the collapse_plot and the critical_concentration_and_plot returned error and both
    // errors are the same, then return this error.
    } elseif ($output_collapse_plot != '' && $output_critical_concentration_and_plot != '' &&  $output_collapse_plot == $output_critical_concentration_and_plot) {
        $response = array(
            "error" => ($output_critical_concentration_and_plot)
        );
    // If both the collapse_plot and the critical_concentration_and_plot returned error and
    // errors are not the same, then return the concatenations of the errors. 
    } elseif ($output_collapse_plot != '' && $output_critical_concentration_and_plot != '') {
        $response = array(
            "error" => ($output_critical_concentration_and_plot  . " " . $output_collapse_plot)
        );
    // Else return generic error.
    } else {
        $response = array(
            "error" => "Unknown error happened. Check your file and try again."
        );
    }

    // FOR DEBUGGING: Response used for debugging. To send to frontend values of required variables. **
    // $response = [
    //     "success" => true,
    //     "manual_ks_append" => $manual_ks_append,
    //     "error" => ""
    // ];

} catch (Exception $e) {
    // Return generic error if the try did not work. 
    $response = [
        "error" => "Unknown error happened. Check your file and try again."
    ];
}

echo json_encode($response);
exit;
?>