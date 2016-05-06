<?php
namespace cs267_hw5\search_program;
error_reporting(E_ERROR);
require_once "index_utils.php";
require_once "ranking_utils.php";
$measures = ["BM25", "DFR"];
if (count($argv) == 4) {
    $index_file = $argv[1];
    $query = $argv[2];
    $relevance_mesaure = $argv[3];
    $stemmed_query_terms = tokenize($query);
    $file_pointer = fopen($index_file, "rb");
    if ($file_pointer && in_array(strtoupper($relevance_mesaure), $measures)) {
        //$results = runQuery($file_pointer, $stemmed_query_terms, $relevance_mesaure);
        //TODO: print results
        exit();
    } else if (!$file_pointer){
        echo "Invalid index file name " . $argv[1];
    }
}
print("\nusage : query_program.php index_filename query relevance_mesaure
        relevance_mesaure -> BM25 | DFR");
