<?php
namespace cs267_hw5\search_program;
require_once "index_utils.php";
require_once "SearchAndRank.php";
$measures = ["BM25", "LMD", "DFR"];
if (count($argv) == 4) {
    $index_file = $argv[1];
    $query = $argv[2];
    $relevance_measure = strtoupper($argv[3]);
    $file_pointer = fopen($index_file, "rb");
    if ($file_pointer && in_array($relevance_measure, $measures)) {
        //TODO: print results in trec_eval format
        $search = new SearchAndRank($file_pointer);
        $stemmed_query_terms = tokenize($query);
        $results = $search->runQuery($stemmed_query_terms, $relevance_measure);
        if ($results) {
            $rank = 1;
            // printing output in trec_eval_top format. Hard coding query_id
            foreach ($results as $docId => $value) {
                print(1 . " " . 0 . " " . $docId . " " . $rank . " " . $value . " my_test\n");
                $rank += 1;
            }
        } else {
            print("\nNo search results found\n");
        }
        fclose($file_pointer);
        exit();
    } else if (!$file_pointer){
        echo "Invalid index file name " . $argv[1];
    }
    fclose($file_pointer);
}
print("\n usage : query_program.php index_filename query relevance_measure
        relevance_measure -> BM25 | LMD");
