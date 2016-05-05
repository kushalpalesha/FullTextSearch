<?php
namespace cs267_hw5\search_program;
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
    //TODO: resolve whether we need to store packed things to file
    $packed_doc_map = pack_document_map($document_map, $doc_offset_map);
    $packed_dict = pack_dict($dictionary, $doc_offset_map);
    $fp = fopen($index_file, "wb");
    $result = fwrite($fp, $packed_dict . $packed_doc_map);
    if ($result) {
        return 1;
    } else {
        return -1;
    }
}

function pack_dict($dictionary, $doc_offset_map)
{
    $primary_array = "";
    $secondary_array = "";
    $postings_list = "";
    $primary_array_offset = 0;
    $secondary_array_offset = 0;
    foreach ($dictionary as $term => $postings) {
        $primary_array = $primary_array . pack("N", $primary_array_offset);
        $secondary_array = $secondary_array . pack("N",strlen($term)) . $term .
            pack("N",$secondary_array_offset);
        $primary_array_offset = strlen($secondary_array);
        // build delta list from postings
        $delta_list = [$doc_offset_map[$postings[0]]];
        for ($i = 1; $i < count($postings); $i++) {
            $delta_list[$i] = $doc_offset_map[$postings[$i]] -
                $doc_offset_map[$postings[$i-1]];
        }
        $compressed_postings = encode_delta_list($delta_list);
        $postings_list = $postings_list . pack("N",strlen($compressed_postings))
            . $compressed_postings;
        $secondary_array_offset = strlen($postings_list);
    }
    $primary_array = pack("N", strlen($primary_array)) . $primary_array;
    return $primary_array . $secondary_array . $postings_list;
}

function encode_delta_list($delta_list)
{
    $compressed_postings = "";
    foreach ($delta_list as $posting) {
        $compressed_postings = $compressed_postings . encode_gamma($posting);
    }
    return $compressed_postings;
}

function encode_gamma($k)
{
    $str = decbin($k);
    $length = strlen($str);
    $str = str_pad($str, 2*$length-1, "0", STR_PAD_LEFT);
    return $str;
}

function pack_document_map($document_map, &$doc_offset_map)
{
    $packed_doc_map = "";
    $offset = 0;
    foreach ($document_map as $document_id => $term_list) {
        $doc_offset_map[$document_id] = $offset;
        ksort($term_list[1]);
        //print_r($term_list);
        $serialized = serialize($term_list);
        $doc_id_len = strlen($document_id);
        $serialized_len = strlen($serialized);
        $packed = pack("N",$doc_id_len).$document_id.
            pack("N",$serialized_len).$serialized;
        $pack_len = strlen($packed);
        $packed_doc_map = $packed_doc_map . $packed;
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
                $document_map[$document_id] = [1, [$term => 1]];
            } else {
                $document_map[$document_id][0] += 1;
                if(!key_exists($term, $document_map[$document_id])) {
                    $document_map[$document_id][1][$term] = 1;
                } else {
                    $document_map[$document_id][1][$term] += 1;
                }
            }
        }
    }
}
