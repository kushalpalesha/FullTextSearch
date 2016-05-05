<?php
namespace cs267_hw5\index_program;
use seekquarry\yioop\library\PhraseParser;
require_once "vendor/autoload.php";

function create_index($path, $index_file)
{
    $dictionary = [];
    $document_map = [];
    $file_list = glob($path);
    if (!$file_list) {
        return 0;
    }
    foreach ($file_list as $file_name) {
        $lines = file($file_name);
        $file_name = basename($file_name);
        $document_id = substr_replace($file_name, "", -4);
        build_index($lines, $document_id, $dictionary, $document_map);
    }
    ksort($dictionary);
    //print_r($dictionary);
    //print_r($document_map);
    $doc_offset_map = [];
    $packed_doc_map = pack_document_map($document_map, $doc_offset_map);
    print($packed_doc_map);
    return 1;
}

function pack_document_map($document_map, &$doc_offset_map)
{
    $packed_doc_map = "";
    $offset = 0;
    foreach ($document_map as $document_id => $term_list) {
        $doc_offset_map[$document_id] = $offset;
        ksort($term_list);
        $serialized = serialize($term_list);
        $doc_id_len = strlen($document_id);
        $serialized_len = strlen($serialized);
        $packed = pack("Na".$doc_id_len."Na".$serialized_len,$doc_id_len,$document_id,$serialized_len,$serialized);
        $pack_len = strlen($packed);
        $packed_doc_map = $packed_doc_map . pack("Na".$pack_len,$pack_len,$packed);
        $offset = $offset + $pack_len;
    }
    return $packed_doc_map;
}

function tokenize($line)
{
    $line = trim(strtolower($line));
    $line = preg_replace("/[[:punct:]]/", "", $line);
    return PhraseParser::stemTerms($line,"en-US");
}


function build_index($lines, $document_id, &$dictionary, &$document_map)
{
    foreach ($lines as $line) {
        $wordlist = tokenize($line);
        foreach ($wordlist as $term) {
            //dictionary
            if (!key_exists($term, $dictionary)) {
                $dictionary[$term] = [$document_id];
            } else if (!in_array($document_id, $dictionary[$term])){
                $dictionary[$term][] = $document_id;
            }
            //document map
            if (!key_exists($document_id, $document_map)) {
                $document_map[$document_id] = [$term => 1];
            } else {
                if(!key_exists($term, $document_map[$document_id])) {
                    $document_map[$document_id][$term] = 1;
                } else {
                    $document_map[$document_id][$term] += 1;
                }
            }
        }
    }
}
