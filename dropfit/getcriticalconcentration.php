<?php

//load concrete 5 file libraries
Loader::library('file/importer');
Loader::model('file_set');

global $chr_to_remove_from_filenames; // if you want a global array in PhP you need to declare it like this and then inside each funciton you either rewrite "global $chr_to_remove_from_filenames;" or you call it as $GLOBALS["chr_to_remove_from_filenames"]
$chr_to_remove_from_filenames= array( "(",")","[","]",">","{","}","<","/","\\","*","?","\"", "+", ",", ";", ":", ".", "|","\t","\r", "\n", " ");


function load_file($debug, $fileSetName)
{//http://www.concrete5.org/documentation/developers/files/files-and-file-versions
             
             $pathToFile = $_FILES['userfile']['tmp_name'];
             $nameOfFile = $_FILES['userfile']['name'];
             $concrete_path='/var/www/html/'; 
             //CHECK WHETHER FILE CAN BE UPLOADED
             $cf = Loader::helper('file');
             $fp = FilePermissions::getGlobal();
             if (!$fp->canAddFiles())
                {
                        echo 'ERROR: Unable to add files. permission issue\n';
                        return array (0, FileImporter::E_FILE_INVALID,0 );
                }
             if (!$fp->canAddFileType($cf->getExtension($pathToFile)))
                {
                        echo "ERROR: Invalid file extension\n";
                        return array ( 0, FileImporter::E_FILE_INVALID_EXTENSION,0 );
                }

//             Loader::library('file/importer');
//             Loader::model('file_set');
             $fi = new FileImporter();
             $fs = FileSet::getByName($fileSetName);
             $fsp = new Permissions($fs);// load file set permissions. These are defined within concrete5i
             //if($debug==1){print_r($fs); print_r($fsp); print_r($fi);print_r($_FILES['userfile']);}
             //note that userfile is the name of the file as defined in the upload form
             
             $pathToFile = $_FILES['userfile']['tmp_name'];
             $nameOfFile = $_FILES['userfile']['name'];
           
             if($debug==1){ echo "pathToFile: ".$pathToFile."   nameOfFile: ".$nameOfFile."\n";}
             $myFileObject = $fi->import($pathToFile, $nameOfFile);
             

             if (is_object($myFileObject))
             {
                if($debug==1){ echo "\nFILE added to set " . $fileSetName . "\n"; }
                $fs->addFileToSet($myFileObject);//add file to file set. this should also set its permissions. 
             }else{
                 echo "\n***  CANNOT UPLOAD INPUT FILE  ***\n";
                 echo "  file name: ".$nameOfFile."\n";
                 echo FileImporter::getErrorMessage($myFileObject);
                 echo "\n\n******************************************************************\n";
                 echo   "Please note that the currently allowed maximum size of a file is: ";
                 echo ini_get( "upload_max_filesize" );
                 echo "\nand a compiled form cannot have a total size larger than: ";
                 echo ini_get( "post_max_size" );
                 echo   "\nIf your file is valid with the correct extension and meets\nthe above criteria please contact us and attach your file.\n";
                 echo   "\n";
                 return array( 0, FileImporter::E_PHP_NO_FILE,0);
             }

      

             $pathf=$concrete_path.$myFileObject->getRelativePath();
             $input_file_complete_path=escapeshellcmd( $pathf);
             
             return array(1, $myFileObject, $input_file_complete_path);
}

// // Enable error reporting
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// require("vendruscolo_server_lib.php");
require('coh_server_lib.php');//contains function check_user

check_user(0, $check_academic=1 ); // first variable 0/1 is debug, it echo to screen the User ID and whether it's academic

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
        "error" => "Unknown error happened. Check your file and try again."
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
        $output_critical_concentration_and_plot = exec("python " . escapeshellarg($absolute_concentration_and_plot_script_path) . " --debug --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $manual_ks_append . $concentrations_to_omit_append);
        $output_collapse_plot = exec("python " . escapeshellarg($absolute_collapse_plot_script_path) . " --debug --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $concentrations_to_omit_append);    
    }
    else {
        $output_critical_concentration_and_plot = exec("python " . escapeshellarg($absolute_concentration_and_plot_script_path) . " --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $manual_ks_append . $concentrations_to_omit_append);
        $output_collapse_plot = exec("python " . escapeshellarg($absolute_collapse_plot_script_path) . " --data_path " . escapeshellarg($temp_file_path) . " --directory_to_store " . escapeshellarg($absolute_writable_folder_path) . " --out_id "  . escapeshellarg($out_id) . $concentrations_to_omit_append);
    }

    // Prepare paths to files that should be outputs of the scripts that were run - (get_critical_concentration_and_plot.py 
    // and get_collapse_plot.py)
    $concentration_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_concentration_plot.png";
    $concentration_usage_path = $absolute_writable_folder_path . '/' . $out_id . '_concentration_usage.json';
    $concentration_file_path = $absolute_writable_folder_path . '/' . $out_id . '_concentration.txt';
    $collapse_plot_file_path= $absolute_writable_folder_path . '/' . $out_id . "_collapse_plot.png";


    // If all these files exist and there were no errors comming from the scripts
    // then get contents of these files and process them accordingly
    if(file_exists($concentration_plot_file_path) && file_exists($concentration_file_path)
                                    && file_exists($concentration_usage_path)
                                    && $output_critical_concentration_and_plot === '' 
                                    && file_exists($collapse_plot_file_path)
                                    && $output_collapse_plot === "") {
        // Read the contents of the file into a string
        $concentration_plot = file_get_contents($concentration_plot_file_path);
        $concentration = file_get_contents($concentration_file_path);
        $concentration_usage_raw = file_get_contents($concentration_usage_path);
        $concentration_usage = json_decode($concentration_usage_raw, true);
        
        $collapse_plot = file_get_contents($collapse_plot_file_path);

        // If the files have corrupted content, send error message to the frontend.
        // If the contents of the files are okay, then send relevant information 
        // to the frontend.
        if ($concentration_plot === false || $concentration===false 
            || $concentration_usage_raw===false || $collapse_plot ===false 
            || empty($concentration_plot) || empty($concentration) 
            || empty($concentration_usage_raw) || empty($collapse_plot)) {
            $response = array(
                "error" => "Unknown error happened. Check your file and try again."
            );
        } else {
            $response = array(
                "concentration_plot" => base64_encode($concentration_plot), // send plot content directly
                "concentration" => $concentration,
                "collapse_plot" => base64_encode($collapse_plot), // send plot content directly
                "concentration_usage" => $concentration_usage
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
    //     "output_critical_concentration_and_plot" => $output_critical_concentration_and_plot,
    //     "absolute_writable_folder_path" => $absolute_writable_folder_path,
    //     "concentrations_to_omit_append" => $concentrations_to_omit_append,
    //     "collapse_plot_file_path_exists" => $collapse_plot_file_path_exists,
    //     "fake_collapse_plot_file_path_exists" => $fake_collapse_plot_file_path_exists,
    //     "error" => ""
    // ];

} catch (Exception $e) {
    // Return generic error if the try did not work. 
    $response = [
        "error" => "Unknown error happened. Check your file and try again."
    ];
}

// Return the response
echo json_encode($response);
exit;
?>
