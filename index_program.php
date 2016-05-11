<?php
namespace cs267_hw5\search_program;
require_once "index_utils.php";
if (count($argv) == 3) {
    $path = rtrim($argv[1],"/") . "/*.txt";
    $index_file = $argv[2];
    $code = create_index($path, $index_file);
    if ($code == 1) {
        echo "Index created and stored in: " . $index_file . ".idx";
    } else if ($code == 0) {
        echo "No .txt files found at " . $path;
    } else {
        echo "An error occurred trying to write index file";
    }
} else {
    echo "usage : search_program.php path_to_folder_to_index index_filename";
}
