<?php # (jEdit options) :folding=explicit:collapseFolds=1:
/*****************************************************************************
    Provides kinemage-creation functions for visualizing various
    aspects of the analysis.
*****************************************************************************/
require_once(MP_BASE_DIR.'/lib/pdbstat.php');
require_once(MP_BASE_DIR.'/lib/analyze.php');

#{{{ makeSidechainDots - appends sc Probe dots
############################################################################
function makeSidechainDots($infile, $outfile)
{
    exec("probe -quiet -noticks -name 'sc-x dots' -self 'alta' $infile >> $outfile");
}
#}}}########################################################################

#{{{ makeMainchainDots - appends mc Probe dots
############################################################################
function makeMainchainDots($infile, $outfile)
{
    exec("probe -quiet -noticks -name 'mc-mc dots' -mc -self 'mc alta' $infile >> $outfile");
}
#}}}########################################################################

#{{{ makeRamachandranKin - creates a kinemage-format Ramachandran plot
############################################################################
function makeRamachandranKin($infile, $outfile)
{
    exec("java -cp ".MP_BASE_DIR."/lib/hless.jar hless.Ramachandran -nosummary $infile > $outfile");
}
#}}}########################################################################

#{{{ makeRamachandranPDF - creates a multi-page PDF-format Ramachandran plot
############################################################################
function makeRamachandranPDF($infile, $outfile)
{
    exec("java -cp ".MP_BASE_DIR."/lib/chiropraxis.jar chiropraxis.rotarama.Ramalyze -pdf $infile $outfile");
}
#}}}########################################################################

#{{{ [NOT SUPPORTED] convertKinToPostscript - uses Mage to do EPS output
############################################################################
// Would have to add Mage to bin/ for this to work again.
/**
* Outputs are named $infile.1.eps, $infile.2.eps, etc.
* One page is generated per frame of animation.
* /
function convertKinToPostscript($infile)
{
    exec("mage -postscript $infile");
}
*/
#}}}########################################################################

#{{{ makeCbetaDevBalls - plots CB dev in 3-D, appending to the given file
############################################################################
function makeCbetaDevBalls($infile, $outfile)
{
    exec("prekin -append -cbetadev $infile >> $outfile");
}
#}}}########################################################################

#{{{ makeCbetaDevPlot - creates a 2-D kinemage scatter plot
############################################################################
function makeCbetaDevPlot($infile, $outfile)
{
    exec("prekin -cbdevdump $infile | java -cp ".MP_BASE_DIR."/lib/hless.jar hless.CBScatter > $outfile");
}
#}}}########################################################################

#{{{ makeMulticritKin - display all quality metrics at once in 3-D
############################################################################
/**
* $infiles is an array of one or more PDB files to process
* $outfile will be overwritten.
* $opt controls what will be output. Each key below maps to a boolean:
*   Bribbons            ribbons colored by B-factor
*   altconf             alternate conformations
*   rama                Ramachandran outliers
*   rota                rotamer outliers
*   cbdev               C-beta deviations greater than 0.25A
*   dots                all-atom contacts dots
*       hbdots          H-bond dots
*       vdwdots         van der Waals (contact) dots
* $nmrConstraints is optional, and if present will generate lines for violated NOEs
*/
function makeMulticritKin($infiles, $outfile, $opt, $nmrConstraints = null)
{
    if(file_exists($outfile)) unlink($outfile);
    
    $stats = describePdbStats( pdbstat(reset($infiles)), false );
    $h = fopen($outfile, 'a');
    fwrite($h, "@text\n");
    foreach($stats as $stat) fwrite($h, "[+]   $stat\n");
    if(count($infiles) > 0) fwrite($h, "Statistics for first file only; ".count($infiles)." total files included in kinemage.\n");
    fwrite($h, "@kinemage 1\n");
    fclose($h);
    
    foreach($infiles as $infile)
    {
        exec("prekin -quiet -mchb -lots -append -animate -onegroup -show 'mc(white),sc(blue)' $infile >> $outfile");
        
        if($opt['Bribbons'])        makeBfactorRibbons($infile, $outfile);
        if($opt['altconf'])         makeAltConfKin($infile, $outfile);
        if($opt['rama'])            makeBadRamachandranKin($infile, $outfile);
        if($opt['rota'])            makeBadRotamerKin($infile, $outfile);
        if($opt['cbdev'])           makeBadCbetaBalls($infile, $outfile);
        if($opt['dots'])            makeBadDotsVisible($infile, $outfile, $opt['hbdots'], $opt['vdwdots']);
        if($nmrConstraints)
            exec("noe-display -cv -s viol -ds+ -fs -k $infile $nmrConstraints < /dev/null >> $outfile");
    }
}
#}}}########################################################################

#{{{ makeAltConfKin - appends mc and sc alts
############################################################################
function makeAltConfKin($infile, $outfile, $mcColor = 'yellow', $scColor = 'gold')
{
    $alts   = findAltConfs($infile);
    $mcGrp  = groupAdjacentRes(array_keys($alts['mc']));
    $scGrp  = groupAdjacentRes(array_keys($alts['sc']));
    $mc     = resGroupsForPrekin($mcGrp);
    $sc     = resGroupsForPrekin($scGrp);
    
    foreach($mc as $mcRange)
        exec("prekin -quiet -append -nogroup -listmaster 'mc alts' -bval -scope $mcRange -show 'mc($mcColor)' $infile >> $outfile");

    foreach($sc as $scRange)
        exec("prekin -quiet -append -nogroup -listmaster 'sc alts' -bval -scope $scRange -show 'sc($scColor)' $infile >> $outfile");
}
#}}}########################################################################

#{{{ resGroupsForPrekin - converts residue groups to Prekin switches
############################################################################
/**
* Takes a group of residues in the format produced by
* lib/analyze.php:groupAdjacentRes() and returns an array of
* Prekin switches specifying those residues:
*   -chainid _ -range "1-2,4-5,10-10"
*   -chainid A -range "1-47,100-101"
* etc.
*/
function resGroupsForPrekin($data)
{
    $out = array();
    foreach($data as $chainID => $chain)
    {
        if($chainID == ' ') $chainID = '_';
        $line   = "-chainid $chainID -range \"";
        $comma  = false;
        foreach($chain as $run)
        {
            reset($run);
            $first  = trim(substr(current($run), 1, 4));;
            $last   = trim(substr(end($run), 1, 4));
            if($comma) $line .= ',';
            else $comma = true;
            $line .= "$first-$last";
        }
        $line .= '"';
        $out[$chainID] = $line;
    }
    return $out;
}
#}}}########################################################################

#{{{ makeBadRamachandranKin - appends mc of Ramachandran outliers
############################################################################
/**
* rama is the data from loadRamachandran(),
* or null to have the data generated automatically.
*/
function makeBadRamachandranKin($infile, $outfile, $rama = null, $color = 'red')
{
    if(!$rama)
    {
        $tmp = tempnam(MP_BASE_DIR."/tmp", "tmp_rama_");
        runRamachandran($infile, $tmp);
        $rama = loadRamachandran($tmp);
        unlink($tmp);
    }
    
    foreach($rama as $res)
    {
        if($res['eval'] == 'OUTLIER')
            $worst[] = $res['resName'];
    }
    $mc = resGroupsForPrekin(groupAdjacentRes($worst));
    
    $h = fopen($outfile, 'a');
    fwrite($h, "@subgroup {Rama outliers} dominant\n");
    fclose($h);
    foreach($mc as $mcRange)
        exec("prekin -append -nogroup -listmaster 'Rama Outliers' -scope $mcRange -show 'mc($color)' $infile >> $outfile");
}
#}}}########################################################################

#{{{ makeBadRotamerKin - appends sc of bad rotamers
############################################################################
/**
* rota is the data from loadRotamer(),
* or null to have it generated on the fly.
*/
function makeBadRotamerKin($infile, $outfile, $rota = null, $color = 'orange', $cutoff = 1.0)
{
    if(!$rota)
    {
        $tmp = tempnam(MP_BASE_DIR."/tmp", "tmp_rota_");
        runRotamer($infile, $tmp);
        $rota = loadRotamer($tmp);
        unlink($tmp);
    }

    foreach($rota as $res)
    {
        if($res['scorePct'] <= $cutoff)
            $worst[] = $res['resName'];
    }
    $sc = resGroupsForPrekin(groupAdjacentRes($worst));
    
    $h = fopen($outfile, 'a');
    fwrite($h, "@subgroup {bad rotamers} dominant\n");
    fclose($h);
    foreach($sc as $scRange)
        exec("prekin -quiet -append -nogroup -listmaster 'rotamer outliers' -bval -scope $scRange -show 'sc($color)' $infile >> $outfile");
}
#}}}########################################################################

#{{{ makeBadCbetaBalls - plots CB dev in 3-D, appending to the given file
############################################################################
function makeBadCbetaBalls($infile, $outfile)
{
    // C-beta deviation balls >= 0.25A
    $cbeta_dev_script = 
'BEGIN { doprint = 0; bigbeta = 0; }
$0 ~ /^@/ { doprint = 0; }
$0 ~ /^@(point)?master/ { print $0 }
$0 ~ /^@balllist/ { doprint = 1; print $0; }
match($0, /^\{ cb .+ r=([0-9]\.[0-9]+) /, frag) { gsub(/gold|pink/, "magenta"); bigbeta = (frag[1]+0 >= 0.25); }
doprint && bigbeta';
    
    exec("prekin -append -quiet -cbetadev $infile | gawk '$cbeta_dev_script' >> $outfile");
}
#}}}########################################################################

#{{{ makeBadDotsVisible - appends Probe dots with only bad clashes visible
############################################################################
/**
* Documentation for this function.
*/
function makeBadDotsVisible($infile, $outfile, $hbDots = false, $vdwDots = false)
{
    $options = "";
    if(!$hbDots)    $options .= " -nohbout";
    if(!$vdwDots)   $options .= " -novdwout";
    
    $dots_off_script =
'$0 ~ /^@(dot|vector)list .* master=\{wide contact\}/ { $0 = $0 " off" }
$0 ~ /^@(dot|vector)list .* master=\{close contact\}/ { $0 = $0 " off" }
$0 ~ /^@(dot|vector)list .* master=\{small overlap\}/ { $0 = $0 " off" }
$0 ~ /^@(dot|vector)list .* master=\{H-bonds\}/ { $0 = $0 " off" }
{print $0}';

    exec("probe $options -4H -quiet -noticks -nogroup -mc -self 'alta' $infile | gawk '$dots_off_script' >> $outfile");
}
#}}}########################################################################

#{{{ makeBfactorRibbons - make ribbon kin color-coded by C-alpha temp. factor
############################################################################
/**
* Create a ribbon colored by B-value, using a black-body radiation scale.
*
* The mode==1 block extracts CA B-values from a PDB file
* The mode==2 block reads kinemage lines,
*   looks up the B-value of a given residue CA,
*   compares it to the rest of the structure to determine a color,
*   inserts the color name and writes the modified line.
*
* Output will be appended onto outfile.
*/
function makeBfactorRibbons($infile, $outfile)
{
    $bbB_ribbon_script =
'BEGIN { mode = 0; }
FNR == 1 {
    mode += 1;
    if(mode == 2) {
        # Correct for multiple atoms
        for(res in bvals)
        {
            if(atomcnt[res] > 0)
                bvals[res] = bvals[res] / atomcnt[res];
        }
        # Sort and determine threshholds
        size = asort(bvals, sortedbs);
        b1 = int((10 * size) / 100);
        b1 = sortedbs[b1];
        b2 = int((20 * size) / 100);
        b2 = sortedbs[b2];
        b3 = int((30 * size) / 100);
        b3 = sortedbs[b3];
        b4 = int((40 * size) / 100);
        b4 = sortedbs[b4];
        b5 = int((50 * size) / 100);
        b5 = sortedbs[b5];
        b6 = int((60 * size) / 100);
        b6 = sortedbs[b6];
        b7 = int((70 * size) / 100);
        b7 = sortedbs[b7];
        b8 = int((80 * size) / 100);
        b8 = sortedbs[b8];
        b9 = int((90 * size) / 100);
        b9 = sortedbs[b9];
        b10 = int((95 * size) / 100);
        b10 = sortedbs[b10];
    }
}
mode==1 && match($0, /ATOM  ...... (N |CA|C |O )  (...) (.)(....)(.)/, frag) {
    resno = frag[4] + 0;
    reslbl = tolower( frag[2] " " frag[3] " " resno frag[5] );
    bvals[reslbl] += substr($0, 61, 6) + 0;
    atomcnt[reslbl] += 1;
}
mode==2 && match($0, /(^\{ *[^ ]+ ([^}]+))(\} *[PL] )(.+$)/, frag) {
    reslbl = frag[2];
    bval = bvals[reslbl];
    if(bval >= b10) color = "white";
    else if(bval >= b9) color = "yellowtint";
    else if(bval >= b8) color = "yellow";
    else if(bval >= b7) color = "gold";
    else if(bval >= b6) color = "orange";
    else if(bval >= b5) color = "red";
    else if(bval >= b4) color = "hotpink";
    else if(bval >= b3) color = "magenta";
    else if(bval >= b2) color = "purple";
    else if(bval >= b1) color = "blue";
    else color = "gray";
    $0 = frag[1] " B" bval frag[3] color " " frag[4];
}
mode==2 { print $0; }';

    $tmp = tempnam(MP_BASE_DIR."/tmp", "tmp_kin_");
    exec("prekin -append -bestribbon -nogroup $infile > $tmp");
    exec("gawk '$bbB_ribbon_script' $infile $tmp >> $outfile");
    unlink($tmp);
}
#}}}########################################################################

#{{{ makeFlipkin - runs Flipkin to generate a summary of the Reduce -build changes
############################################################################
/**
*/
function makeFlipkin($inpath, $outpathAsnGln, $outpathHis)
{
    // $_SESSION[hetdict] is used to set REDUCE_HET_DICT environment variable,
    // so it doesn't need to appear on the command line here.
    exec("flipkin -limit" . MP_REDUCE_LIMIT . " $inpath > $outpathAsnGln");
    exec("flipkin -limit" . MP_REDUCE_LIMIT . " -h $inpath > $outpathHis");
}
#}}}########################################################################

#{{{ makeMulticritChart - display all quality metrics at once in 2-D
############################################################################
/**
* $outfile will be overwritten with an HTML table.
* $clash    is the data structure from loadClashlist()
* $rama     is the data structure from loadRamachandran()
* $rota     is the data structure from loadRotamer()
* $cbdev    is the data structure from loadCbetaDev()
* $sortBy   can be 'natural', 'bad', 'clash', 'rama', 'rota', 'cbdev'
* Any of them can be set to null if the data is unavailable.
*/
function makeMulticritChart($infile, $outfile, $clash, $rama, $rota, $cbdev, $sortBy = 'natural')
{
    // Make sure all residues are represented, and in the right order.
    $res = listResidues($infile);
    foreach($res as $k => $v) $res[$k] = array('cnit' => $v);
    
    if(is_array($clash))
    {
        foreach($clash['clashes'] as $cnit => $worst)
        {
            $res[$cnit]['clash_val'] = $worst;
            $res[$cnit]['clash'] = "<td bgcolor='#ff6699'>$worst&Aring;</td>";
            $res[$cnit]['isbad'] = true;
        }
    }
    if(is_array($rama))
    {
        foreach($rama as $item)
        {
            $res[$item['resName']]['rama_val'] = $item['scorePct'];
            if($item['eval'] == "OUTLIER")
            {
                $res[$item['resName']]['rama'] = "<td bgcolor='#ff6699'>$item[eval] ($item[scorePct]%)<br><small>$item[type] - $item[phi],$item[psi]</small></td>";
                $res[$item['resName']]['isbad'] = true;
            }
            else
                $res[$item['resName']]['rama'] = "<td>$item[eval] ($item[scorePct]%)<br><small>$item[type] / $item[phi],$item[psi]</small></td>";
        }
    }
    if(is_array($rota))
    {
        foreach($rota as $item)
        {
            $res[$item['resName']]['rota_val'] = $item['scorePct'];
            if($item['scorePct'] <= 1.0)
            {
                $res[$item['resName']]['rota'] = "<td bgcolor='#ff6699'>$item[scorePct]%<br><small>angles: $item[chi1],$item[chi2],$item[chi3],$item[chi4]</small></td>";
                $res[$item['resName']]['isbad'] = true;
            }
            else
                $res[$item['resName']]['rota'] = "<td>$item[scorePct]%<br><small>angles: $item[chi1],$item[chi2],$item[chi3],$item[chi4]</small></td>";
        }
    }
    if(is_array($cbdev))
    {
        foreach($cbdev as $item)
        {
            $res[$item['resName']]['cbdev_val'] = $item['dev'];
            if($item['dev'] >= 0.25)
            {
                $res[$item['resName']]['cbdev'] = "<td bgcolor='#ff6699'>$item[dev]A</small></td>";
                $res[$item['resName']]['isbad'] = true;
            }
            else
                $res[$item['resName']]['cbdev'] = "<td>$item[dev]&Aring;</small></td>";
        }
    }
    
    // Sort into user-defined order
    if($sortBy == 'natural')        {} // don't change order
    elseif($sortBy == 'bad')        uasort($res, 'mcSortBad');
    elseif($sortBy == 'clash')      uasort($res, 'mcSortClash');
    elseif($sortBy == 'rama')       uasort($res, 'mcSortRama');
    elseif($sortBy == 'rota')       uasort($res, 'mcSortRota');
    elseif($sortBy == 'cbdev')      uasort($res, 'mcSortCbDev');
    
    $out = fopen($outfile, 'wb');
    fwrite($out, "<table width='100%' cellspacing='1' border='0'>\n");
    fwrite($out, "<tr align='center' bgcolor='".MP_TABLE_HIGHLIGHT."'>\n");
    fwrite($out, "<td><b>Res</b></td>\n");
    if(is_array($clash))  fwrite($out, "<td><b>Clash &gt; 0.4&Aring;</b></td>\n");
    if(is_array($rama))   fwrite($out, "<td><b>Ramachandran</b></td>\n");
    if(is_array($rota))   fwrite($out, "<td><b>Rotamer</b></td>\n");
    if(is_array($cbdev))  fwrite($out, "<td><b>C&beta; deviation</b></td>\n");
    fwrite($out, "</tr>\n");
    
    $color = MP_TABLE_ALT1;
    foreach($res as $cnit => $eval)
    {
        fwrite($out, "<tr align='center' bgcolor='$color'>");
        fwrite($out, "<td align='left'>$cnit</td>");
        if(is_array($clash))
        {
            if(isset($eval['clash']))   fwrite($out, $eval['clash']);
            else                        fwrite($out, "<td>-</td>");
        }
        if(is_array($rama))
        {
            if(isset($eval['rama']))    fwrite($out, $eval['rama']);
            else                        fwrite($out, "<td>-</td>");
        }
        if(is_array($rota))
        {
            if(isset($eval['rota']))    fwrite($out, $eval['rota']);
            else                        fwrite($out, "<td>-</td>");
        }
        if(is_array($cbdev))
        {
            if(isset($eval['cbdev']))   fwrite($out, $eval['cbdev']);
            else                        fwrite($out, "<td>-</td>");
        }
        fwrite($out, "</tr>\n");
        $color == MP_TABLE_ALT1 ? $color = MP_TABLE_ALT2 : $color = MP_TABLE_ALT1;
    }
    fwrite($out, "</table>\n");
    fclose($out);
}
#}}}########################################################################

#{{{ mcSortXXX - sort functions for multicriterion chart
############################################################################
// We need this b/c sort is not guaranteed to preserve starting order
// for elements that compare as equal.
function mcSortNatural($a, $b)
{
    if($a['cnit'] < $b['cnit'])     return -1;
    elseif($a['cnit'] > $b['cnit']) return 1;
    else                            return 0;
}

function mcSortBad($a, $b)
{
    if($a['isbad'])
    {
        if($b['isbad']) return mcSortNatural($a, $b);
        else            return -1;
    }
    elseif($b['isbad']) return 1;
    else                return mcSortNatural($a, $b);
}

function mcSortClash($a, $b)
{
    if($a['clash_val'] < $b['clash_val'])       return 1;
    elseif($a['clash_val'] > $b['clash_val'])   return -1;
    else                                        return mcSortNatural($a, $b);
}

function mcSortRama($a, $b)
{
    // unset values compare as zero and sort to top otherwise
    if(!isset($a['rama_val']))
    {
        if(!isset($b['rama_val']))          return mcSortNatural($a, $b);
        else                                return 1;
    }
    elseif(!isset($b['rama_val']))          return -1;
    elseif($a['rama_val'] < $b['rama_val']) return -1;
    elseif($a['rama_val'] > $b['rama_val']) return 1;
    else                                    return mcSortNatural($a, $b);
}

function mcSortRota($a, $b)
{
    // unset values compare as zero and sort to top otherwise
    if(!isset($a['rota_val']))
    {
        if(!isset($b['rota_val']))          return mcSortNatural($a, $b);
        else                                return 1;
    }
    elseif(!isset($b['rota_val']))          return -1;
    elseif($a['rota_val'] < $b['rota_val']) return -1;
    elseif($a['rota_val'] > $b['rota_val']) return 1;
    else                                    return mcSortNatural($a, $b);
}

function mcSortCbDev($a, $b)
{
    if($a['cbdev_val'] < $b['cbdev_val'])       return 1;
    elseif($a['cbdev_val'] > $b['cbdev_val'])   return -1;
    else                                        return mcSortNatural($a, $b);
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