<?PHP
require('artnum/PDF.php');
define('FONTNAME', 'LiberationSans');

function remove_emoji($string) {
    return $string;
    // Match Emoticons
    $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
    $clear_string = preg_replace($regex_emoticons, '~', $string);

    // Match Miscellaneous Symbols and Pictographs
    $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
    $clear_string = preg_replace($regex_symbols, '~', $clear_string);

    // Match Transport And Map Symbols
    $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
    $clear_string = preg_replace($regex_transport, '~', $clear_string);

    // Match Miscellaneous Symbols
    $regex_misc = '/[\x{2600}-\x{26FF}]/u';
    $clear_string = preg_replace($regex_misc, '~', $clear_string);

    // Match Dingbats
    $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
    $clear_string = preg_replace($regex_dingbats, '~', $clear_string);

    return $clear_string;
}

if (empty($_FILES)) { return; }


$fp = fopen($_FILES['wa']['tmp_name'], 'r');
if (!$fp) { return; }
$persons = [];
$messages = [];
$date = null;
$time = null;
$person = null;
$txt = null;
$i = 0;
$msgCount = 0;
while (($line = fgets($fp)) !== FALSE) {
    if (preg_match('/^([0-9\.]+)\s.*\s([0-9:]+)\s-\s([^:]+):(.*)$/', $line, $matches)) {
        $date = $matches[1];
        $time = $matches[2];
        $person = $matches[3];
        $txt = $matches[4];
        
        if (!in_array($person, $persons)) {
            $persons[] = $person;
        }

        $d = DateTime::createFromFormat('d.m.y', $date);
        $ts = $d->getTimestamp();
        if (empty($messages[$ts])) {
            $messages[$ts] = [];
            $i = 0;
        } else {
            $i++;
        }
        $messages[$ts][$i] = [
            'time' => $time,
            'person' => $person,
            'txt' => $txt
        ];
        $msgCount++;
    } else {
        if ($person !== null) {
            $messages[$ts][$i]['txt'] .= PHP_EOL . $line;
        }
    }
}
unlink($_FILES['wa']['tmp_name']);

class WAPDF extends artnum\PDF {
    function Footer () {
        $this->SetFont(FONTNAME);
        $this->SetFontSize(2);
        $this->printLn('Page ' . $this->PageNo(), ['align' => 'right']);
    }
}

$pdf = new WAPDF();
$pdf->SetMargins(20, 20, 20);
$pdf->AddFont(FONTNAME, '', 'LiberationSans-Regular.ttf', true);
$pdf->AddFont(FONTNAME, 'B', 'LiberationSans-Bold.ttf', true);
$pdf->AddFont(FONTNAME, 'I', 'LiberationSans-Italic.ttf', true);
$pdf->AddFont(FONTNAME, 'BI', 'LiberationSans-BoldItalic.ttf', true);
$pdf->AddPage();
$pdf->SetFontSize(3);

$pdf->SetFont('LiberationSans', 'B');
$pdf->printLn('Messages WhatsApp entre :');
$pdf->SetFont(FONTNAME);
$maxWidth = 0;
foreach($persons as $person) {
    $pdf->printLn(' - ' . $person);
    $w = $pdf->GetStringWidth($person);
    if ($w > $maxWidth) { $maxWidth = $w; }
}
$pdf->br();

ksort($messages);
$keys = array_keys($messages);
$firstDate = new DateTime();
$firstDate->setTimestamp($keys[0]);
$lastDate = new DateTime();
$lastDate->setTimestamp($keys[count($keys) - 1]);


$pdf->printLn($msgCount . ' Messages dans la conversation.');
$pdf->printLn('Du ' . $firstDate->format('d.m.Y') . ' au ' . $lastDate->format('d.m.Y'));

$pdf->addTab(10);
$pdf->addTab($maxWidth + 25);
$left = 210 - ($maxWidth + 75);
$msgId = 1;

foreach ($messages as $ts => $msg) {
    $d = new DateTime();
    $d->setTimestamp($ts);
    $date = $d->format('d.m.Y');
    $pdf->SetFont(FONTNAME);
    $pdf->block($date);
    $pdf->br();
    $pdf->printLn('En date du ' . $date, ['underline' => true]);
    foreach ($msg as $m) {
        $pdf->SetFont(FONTNAME);
        $pdf->printLn("$msgId. ", ['break' => false]);
        $pdf->tab(1);
        $pdf->printLn($m['time'] . ',  ', ['break' => false]);
        $pdf->SetFont(FONTNAME, 'I');
        $pdf->printLn($m['person'], ['break' => false]);
        $pdf->tab(2);
        $pdf->SetFont(FONTNAME, 'B');
        $txt = remove_emoji(trim($m['txt']));

        if (strpos($txt, ' ') === FALSE && $pdf->GetStringWidth($txt) > $left) {
            $tmp = '';
            for($i = 0; $i < strlen($txt); $i++) {
                $tmp .= $txt[$i];
                if ($pdf->GetStringWidth($tmp) >= $left) {
                    $pdf->tab(2);
                    $pdf->printLn($tmp);
                    $tmp = '';
                }
            }
            if ($tmp !== '') {
                $pdf->tab(2);
                $pdf->printLn($tmp);
            }
        } else {
            $txt = preg_split ('/$\R?^/m', $txt);
            foreach($txt as $t) {
                if (empty($t)) { continue; }
                $pdf->tab(2);
                $pdf->printLn(trim($t), ['multiline' => true]);
            }
        }
        $msgId++;
    }
    $pdf->close_block();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="wa.pdf"');
$pdf->Output('I');
?>