<?php # (jEdit options) :folding=explicit:collapseFolds=1:
/*****************************************************************************
    Implements lab notebook functions for MolProbity.
    
    The labbook data structure is an associative array where
    the first index is an integer "entry number", and
    the seconds index is one of the following:
    
    'title'     a one-line title for this entry
    'ctime'     the time the entry was created (seconds since the Epoch)
    'modtime'   the time the entry was created (seconds since the Epoch)
    'model'     which model this relates to, or the empty string/undefined
    'keywords'  a bar-separated (|) list of keywords for classifying entries
                Use explode() and implode() to convert to/from an array.
                Stored as a string b/c they're easier to transmit in forms.
                Common keywords:
                    auto        Automatically generated by MP3 code
    'entry'     the user's comments, with HTML formatting
    
*****************************************************************************/
require_once(MP_BASE_DIR.'/lib/timezones.php');
require_once(MP_BASE_DIR.'/lib/strings.php');

#{{{ openLabbook - loads labbook from disk and returns array
############################################################################
function openLabbook()
{
    $file = $_SESSION['dataDir']."/labbook";
    
    // Read in notebook data, if present
    if($fp = @fopen($file, "rb"))
    {
        $bookData = fread($fp, filesize($file));
        $book = unserialize($bookData);
        if(!$book) echo "Unable to unserialize '$bookData'";
        @fclose($fp);
    }
    else $book = array(); // empty array
    
    return $book;
}
#}}}########################################################################

#{{{ saveLabbook - writes labbook array to disk
############################################################################
/**
* Don't try calling openLabbook() right after this!
* Some of the data doesn't get read in correctly,
* but no error is produced in fopen() etc.
*
* Returns false on failure
*/
function saveLabbook($bookData)
{
    $file = $_SESSION['dataDir']."/labbook";

    // Write the notebook data
    if($fp = @fopen($file, "wb"))
    {
        $r = fwrite($fp, serialize($bookData));
        @fclose($fp);
        return $r;
    }
    else return false;
}
#}}}########################################################################

#{{{ newLabbookEntry - creates a new entry
############################################################################
function newLabbookEntry($model = "", $keywords = "")
{
    return array(
        'ctime'     => time(),
        'modtime'   => time(),
        'model'     => $model,
        'keywords'  => $keywords
    );
}
#}}}########################################################################

#{{{ addLabbookEntry - all-in-one automatic entry for background jobs
############################################################################
/**
* Returns the entry number of the new entry.
* Don't call openLabbook() right after using this.
*/
function addLabbookEntry($title, $text, $model = "", $keywords = "")
{
    $labbook = openLabbook();
    $entry = newLabbookEntry($model, $keywords);
    $entry['title'] = $title;
    $entry['entry'] = $text;
    $entryNum = count($labbook);
    $labbook[ $entryNum ] = $entry;
    saveLabbook($labbook);
    
    return $entryNum;
}
#}}}########################################################################

#{{{ formatLabbookEntry - returns HTML rendering of one entry
############################################################################
/**
* $entry - one of the entries from the labbook, i.e. $labbook[N]
*   This should be a single array with keys 'title', 'ctime', etc.
*
* Returns a formatted chunk of HTML that you can insert in your pages.
*/
function formatLabbookEntry($entry)
{
    $s = "";
    $s .= "<b>".$entry['title']."</b>\n";
    $s .= "<br><table border=0 cellpadding=0 cellspacing=3>\n";
    $s .= "<tr align=left valign=top><td><small>Entry begun:</small></td><td><small>".formatDayTime($entry['ctime'])."</small></td></tr>\n";
    $s .= "<tr align=left valign=top><td><small>Last modified:</small></td><td><small>".formatDayTime($entry['modtime'])."</small></td></tr>\n";
    // At the moment, these fields are more for internal use than for the user.
    //if($entry['model'] != "")
    //    $s .= "<tr align=left valign=top><td><small>Model:</small></td><td><small>".$entry['model']."</small></td></tr>\n";
    //if($entry['keywords'] != "")
    //    $s .= "<tr align=left valign=top><td><small>Keywords:</small></td><td><small>".$entry['keywords']."</small></td></tr>\n";
    $s .= "</table>";
    $s .= "<p>".$entry['entry']."\n";
    
    return $s;
}
#}}}########################################################################

#{{{ formEditLabbook - produces a form for creating/editing an entry
############################################################################
/**
* $entry - one of the entries from the labbook, i.e. $labbook[N]
*   This should be a single array with keys 'title', 'ctime', etc.
*   For a new entry, this should be newLabbookEntry().
* $width, $height - the size of the text entry area, in characters.
*
* Returns a formatted chunk of HTML that you can insert in your pages,
* inside a <FORM> element. No submit button is provided.
*
* When submitted, the form will define an array called 'labbookEntry'
* with keys 'title', 'ctime', etc. Look for it in $_REQUEST.
*/
function formEditLabbook($entry, $width = 90, $height = 30)
{
    // HTML entities are interpretted once by the browser inside the form,
    // so title and entry have to be "double protected" to save correctly.
    $s = "";
    $s .= "<input type='text' size='$width' name='labbookEntry[title]' value='".htmlspecialchars($entry['title'])."'>\n";
    $s .= "<input type='hidden' name='labbookEntry[ctime]' value='$entry[ctime]'>\n";
    $s .= "<input type='hidden' name='labbookEntry[modtime]' value='$entry[modtime]'>\n";
    $s .= "<input type='hidden' name='labbookEntry[model]' value='$entry[model]'>\n";
    $s .= "<input type='hidden' name='labbookEntry[keywords]' value='$entry[keywords]'>\n";
    
    $s .= "<br><table border=0 cellpadding=0 cellspacing=3>\n";
    $s .= "<tr align=left valign=top><td>Entry begun:</td><td>".formatDayTime($entry['ctime'])."</td></tr>\n";
    $s .= "<tr align=left valign=top><td>Last modified:</td><td>".formatDayTime($entry['modtime'])."</td></tr>\n";
    $s .= "</table>";
    
    if($entry['model'] != "")
        $s .= "<br>Model: ".$entry['model']."\n";
    if($entry['keywords'] != "")
        $s .= "<br>Keywords: ".implode(", ", explode("|", $entry['keywords']))."\n";

    $s .= "<br><textarea name='labbookEntry[entry]' cols='$width' rows='$height'>".htmlspecialchars($entry['entry'])."</textarea>\n";
    
    return $s;
}
#}}}########################################################################

#{{{ openLabbookWithEdit - opens book, saves edited entry from notebook_edit.php
############################################################################
/**
* This functions checks for affirmative output of notebook_edit.php
* (i.e., that the user pressed the Save button) and if found,
* it saves the new/modified labbook entry.
* It also logs the action in molprobity.log
* This function is intended to simplify in-line notebook editing.
*
* It returns the same result as openLabbook().
* This is because calling saveLabbook immediately followed by openLabbook
* seems to result in some of the supposedly written info being lost,
* at least on my Powerbook and PHP 4.3.2.
*/
function openLabbookWithEdit()
{
    // Load lab notebook data
    $labbook = openLabbook();

    // Did we get an edit request?
    if($_REQUEST['labbookEditCmd'] == "Save")
    {
        if(isset($_REQUEST['entryNumber'])) // Replace an old entry
        {
            $entryNum = $_REQUEST['entryNumber'];
            $labbook[ $entryNum ] = $_REQUEST['labbookEntry'];
            mpLog("notebook-edit:User modified existing lab notebook entry");
        }
        else // Append the new entry
        {
            $entryNum = count($labbook);
            $labbook[ $entryNum ] = $_REQUEST['labbookEntry'];
            mpLog("notebook-add:User added a new entry to the lab notebook");
        }
        
        saveLabbook($labbook);
    }
    
    return $labbook;
}
#}}}########################################################################

#{{{ a_function_definition - sumary_statement_goes_here
############################################################################
/**
* Documentation for this function.
*/
//function someFunctionName() {}
#}}}########################################################################
?>
