<?php

include('../vendor/autoload.php');
use Blocktrail\SDK\BlocktrailSDK;

//enable CORS for heroku app and gh-pages...you might want to disable this when deploying on your own server
header('Access-Control-Allow-Origin: *');  

$apiKey = "76fbd6319b171d12ab8a87c739e1ee16a9e4ba3a";
$apiSecret = "70e1654ce7bc6301c3c33108eee1b42a7f698aa2";
$network = 'BTC';
$testnet = false;
$apiVersion = 'v1';

$client = new BlocktrailSDK($apiKey, $apiSecret, $network, $testnet, $apiVersion);
$client->setCurlDefaultOption('verify', false);

//get the data
$block = $_GET['block'];
if ($block == "latest") {
    $data['block'] = $client->blockLatest();
} else {
    $data['block'] = $client->block($block);
}
$data['transactions'] = $client->blockTransactions($data['block']['height'], $page=1, $limit=500)['data'];

//map of semitones for a hexadec tone conversion (needs 16 tones)
$toneMap = array(
    //Chromatic scale
    ['C','C#','D','Eb','E','F','F#','G','G#','A','Bb','B','C','D','E','F'],
    //pentatonic major
    ['C','E','F','G','B','C','E','F','G','B','C','E','F','G','B','C'],
    //Hexatonic - six note blues
    ['C','D','D#','E','G','A','C','D','D#','E','G','A','C','D','D#','E'],
    //Heptatonic - seven note blues
    ['C','Eb','F','F#','G','Bb','C','Eb','F','F#','G','Bb','C','Eb','F', 'F#'],
    //full tone scale - more pleasant on the ears
    ['C','C','D','E','E','F','F','G','G','A','B','B','C','D','E','F']
 );
$scaleNames = array('Chromatic', 'Pentatonic Major', 'Hexatonic', 'Heptatonic', 'Whole Tone');
//decide the tonemap to use based on the block height (or maybe difficulty?)
$scale = $data['block']['height'] % count($toneMap);

//1. Instruments
$totalInstruments = 4;
$addDrums = false;

//take chunks of x transactions, to create x instruments for melody
//max of 125 notes
foreach ($data['transactions'] as $key => $tx) {
    //parse the transaction to create performance data
    //1. note (tone and pitch, obtained from first and second part of hash)
    $tone = hexdec(substr($tx['hash'], 0, 1));
    $note = $toneMap[$scale][$tone];
    $pitch = min(1, floor(hexdec(substr($tx['hash'], 1, 1))/2.2)); // divide by 2.2 to reduce range from 0-16 to 1-7

    //2. length (convert value to semiquavers)
    $length = floor($tx['estimated_value']/6250000);
    //compress length into range of 1 semiquaver to 1 semibreve
    //or...use time between txs for note length
    //or...use third part of hash to determin the length
    $length = hexdec(substr($tx['hash'], 2, 1));

    //3. panning - controlled by ratio of inputs to outputs
    $inputs = count($tx['inputs']);
    $outputs = count($tx['outputs']);
    //$pan = 

    $performance = array(
        'note'   => $note.$pitch,
        'value'  => $tx['estimated_value'],
        'length' => $length,
        'pan'    => [0, 1, 10],
    );

    //add the performance data to the relevant instrument
    $instrumentIndex = ($key+1) % $totalInstruments;
    $instruments[$instrumentIndex][] = $performance;
}

//parse the block data to determine a drum track?
if ($addDrums) {
    $drumInstrument = count($instruments);
    $length = count($instruments[0]);
    for ($length; $length>0; $length--) {
        $performance = array(
            'note'   => 'A1',
            'value'  => 0,
            'length' => 8,
            'pan'    => 0,
        );
        $instruments[$drumInstrument][] = $performance;
    }
}

//an array of instruction, each index being an incremented semi-quaver
$blockchainScore = array();

//for each instrument, read it's score and create instructions (note, length, pitch info, etc) at the correct position
foreach ($instruments as $instrumentKey => $instrument) {
    $scorePosition = 0;
    foreach ($instrument as $key => $instruction) {
        //put the instruction in the correct place
        $blockchainScore[$scorePosition][$instrumentKey] = $instruction;
        //increment the position to the next point 
        $scorePosition += $instruction['length'];
    }
}
//fill in the blanks - get how may instructions there should be, and create empty instructions for each missing index
end($blockchainScore);
$totalInstructions = key($blockchainScore);
for ($i=0; $i<$totalInstructions; $i++) {
    if (!isset($blockchainScore[$i])) {
        $blockchainScore[$i] = array();
    }
}

$response = array(
    'score' => $blockchainScore,
    'scale' => $scaleNames[$scale],
    'transactions' => $data['transactions'],
    'blockHash' => $data['block']['hash'],
    'blockHeight' => $data['block']['height'],
    'totalTransactions' => count($data['transactions']),

);
echo json_encode($response);
exit;




/*
 Each block is a song. 
 The transactions are the notes:
    the hash determines the instruments notes (split up to x chunks, one for each instrument, and then convert into a tone/semitone)
    the tx value determines the length
    greater tx value = higher pitch?
    

Transactions are the notes:
    take 4 tx at a time - one for each instrument
    use the value to determine the note length (convert into nearest unit: 1bitcoin = 1 semibreve, 0.5: minim, 0.25:crotchet, 0.125: quaver, 0.0625: semi-quaver)
    convert the hash into 



Frontend:
    1.
    create array of instructions, with each index being a semi-quaver. instructions in each saying what to do with each instrument (set config, start play, stop play, etc)
    timeout: reads each index of array and decides what to do

 */