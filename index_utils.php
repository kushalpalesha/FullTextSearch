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
    $corpus_size = 0;
    foreach ($file_list as $file_name) {
        $lines = file($file_name);
        $file_name = basename($file_name);
        $document_id = substr_replace($file_name, "", -4);
        $corpus_size += build_index($lines, $document_id, $dictionary, $document_map);
    }
    ksort($dictionary);
    $doc_offset_map = [];
    $packed_doc_map = pack_document_map($document_map, $doc_offset_map, $corpus_size);
    $packed_dict = pack_dict($dictionary, $doc_offset_map);
    $fp = fopen($index_file . '.idx', "wb");
    $result = fwrite($fp, $packed_dict . $packed_doc_map);
    fclose($fp);
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
    $secondary_array_offset = 0;
    $postings_list_offset = 0;
    foreach ($dictionary as $term => $postings) {
        $primary_array = $primary_array . pack("N", $secondary_array_offset);
        $secondary_array = $secondary_array . pack("N",strlen($term)) . $term .
            pack("N",$postings_list_offset);
        $secondary_array_offset = strlen($secondary_array);
        // build delta list from postings
        $delta_list = [$doc_offset_map[$postings[0]][0]];
        $frequency_list = [$doc_offset_map[$postings[0]][1][$term]];
        for ($i = 1; $i < count($postings); $i++) {
            $delta_list[$i] = $doc_offset_map[$postings[$i][0]] -
                $doc_offset_map[$postings[$i-1][0]];
            $frequency_list[$i] = $doc_offset_map[$postings[$i]][1][$term];
        }
        $compressed_postings = encode_list($delta_list);
        $compressed_frequencies = encode_list($frequency_list);
        $postings_list = $postings_list . pack("N",strlen($compressed_postings))
            . $compressed_postings . pack("N",strlen($compressed_frequencies))
            . $compressed_frequencies;
        $postings_list_offset = strlen($postings_list);
    }
    $primary_array = pack("N", strlen($primary_array)) . $primary_array;
    $secondary_array = pack("N", strlen($secondary_array)) . $secondary_array;
    $postings_list = pack("N", strlen($postings_list)) . $postings_list;
    return $primary_array . $secondary_array . $postings_list;
}

function encode_list($list)
{
    $coded_string = "";
    $compressed_list = "";
    foreach ($list as $number) {
        $coded_string = $coded_string . encode_gamma($number);
        while (strlen($coded_string) > 8) {
            $num = bindec(substr($coded_string, 0, 8));
            $coded_string = substr($coded_string, 8);
            $compressed_list = $compressed_list . chr($num);
        }
    }
    if ($coded_string != "") {
        $coded_string = str_pad($coded_string, 8, "0", STR_PAD_RIGHT);
        $num = bindec($coded_string);
        $compressed_list = $compressed_list . chr($num);
    }
    return $compressed_list;
}

function encode_gamma($k)
{
    $str = decbin($k);
    $length = strlen($str);
    $str = str_pad($str, 2*$length-1, "0", STR_PAD_LEFT);
    return $str;
}

function pack_document_map($document_map, &$doc_offset_map, $corpus_size)
{
    $packed_doc_map = "";
    $offset = 0;
    //TODO:use corpus_size to calculate avg_docLen and store in packed map
    foreach ($document_map as $document_id => $doc_info) {
        $doc_offset_map[$document_id] = [$offset,$doc_info[1]];
        $doc_id_len = strlen($document_id);
        $serialized_len = strlen($serialized);
        $packed = pack("N",$doc_id_len).$document_id.pack("N",$doc_info[0]);
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
                if(!key_exists($term, $document_map[$document_id][1])) {
                    $document_map[$document_id][1][$term] = 1;
                } else {
                    $document_map[$document_id][1][$term] += 1;
                }
            }
        }
    }
    return $document_map[$document_id][0];
}
