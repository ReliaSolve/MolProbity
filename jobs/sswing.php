<?php # (jEdit options) :folding=explicit:collapseFolds=1:
/*****************************************************************************
    This file runs SSWING on a specified set of residues and calculates a
    resulting PDB file.
    
INPUTS (via $_SESSION['bgjob']):
    model           ID code for model to process
    edmap           the map file name
    cnit            a set of CNIT codes for residues to process

OUTPUTS (via $_SESSION['bgjob']):
    Adds a new entry to $_SESSION['models'].
    newModel        the ID of the model just added

*****************************************************************************/
// EVERY *top-level* page must start this way:
// 1. Define it's relationship to the root of the MolProbity installation.
// Pages in subdirectories of lib/ or public_html/ will need more "/.." 's.
    if(!defined('MP_BASE_DIR')) define('MP_BASE_DIR', realpath(dirname(__FILE__).'/..'));
// 2. Include core functionality - defines constants, etc.
    require_once(MP_BASE_DIR.'/lib/core.php');
    require_once(MP_BASE_DIR.'/lib/model.php');
    require_once(MP_BASE_DIR.'/lib/visualize.php');
    require_once(MP_BASE_DIR.'/lib/sswing.php');
// 3. Restore session data. If you don't want to access the session
// data for some reason, you must call mpInitEnvirons() instead.
    session_id( $_SERVER['argv'][1] );
    mpStartSession();
// 4. For pages that want to see the session but not change it, such as
// pages that are refreshing periodically to monitor a background job.
    #mpSessReadOnly();
// 5. Set up reasonable values to emulate CLI behavior if we're CGI
    set_time_limit(0); // don't want to bail after 30 sec!
// 6. Record this PHP script's PID in case it needs to be killed.
    $_SESSION['bgjob']['processID'] = posix_getpid();
    mpSaveSession();
    
#{{{ a_function_definition - sumary_statement_goes_here
############################################################################
/**
* Documentation for this function.
*/
//function someFunctionName() {}
#}}}########################################################################

# MAIN - the beginning of execution for this page
############################################################################
$modelID = $_SESSION['bgjob']['model'];
$model = $_SESSION['models'][$modelID];
$pdb = "$model[dir]/$model[pdb]";
$pdbout = "$model[dir]/$model[prefix]sswing.pdb";
$map = $_SESSION['dataDir'].'/'.$_SESSION['bgjob']['edmap'];
$cnit = $_SESSION['bgjob']['cnit'];

// Set up progress message
foreach($cnit as $res)
    $tasks[$res] = "Process $res with SSWING";
$tasks["combine"] = "Combine all changes and create kinemage";

$all_changes = array();
foreach($cnit as $res)
{
    setProgress($tasks, $res);
    $changes = runSswing($pdb, $map, $model['dir'], $res);
    $all_changes = array_merge($all_changes, $changes);
}

setProgress($tasks, "combine");
pdbSwapCoords($pdb, $pdbout, $all_changes);
makeSswingKin($pdb, $pdbout, "$model[dir]/$model[prefix]sswing.kin", $cnit);
$_SESSION['bgjob']['all_changes'] = $all_changes; //XXX-TMP

//$_SESSION['bgjob']['newModel'] = $id;
setProgress($tasks, null);

############################################################################
// Clean up and go home
unset($_SESSION['bgjob']['processID']);
$_SESSION['bgjob']['endTime']   = time();
$_SESSION['bgjob']['isRunning'] = false;
?>