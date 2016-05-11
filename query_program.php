<?php
namespace cs267_hw5\search_program;
require_once "index_utils.php";
require_once "SearchAndRank.php";
$measures = ["BM25", "DFR"];
if (count($argv) == 4) {
    $index_file = $argv[1];
    $query = $argv[2];
    $relevance_measure = strtoupper($argv[3]);
    $file_pointer = fopen($index_file, "rb");
    if ($file_pointer && in_array($relevance_measure, $measures)) {
        //$results = runQuery($file_pointer, $stemmed_query_terms, $relevance_mesaure);
        //TODO: print results
        $search = new SearchAndRank($file_pointer);
        $stemmed_query_terms = tokenize($query);
        $results = $search->runQuery($stemmed_query_terms, $relevance_measure);
        print_r($results);
        exit();
    } else if (!$file_pointer){
        echo "Invalid index file name " . $argv[1];
    }
}
print("\n usage : query_program.php index_filename query relevance_measure
        relevance_measure -> BM25 | DFR");
