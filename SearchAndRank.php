<?php
namespace cs267_hw5\search_program;

class SearchAndRank
{
    private $primary_array;
    private $secondary_array;
    private $secondary_array_start;
    private $postings_list_start;
    private $document_map_start;

    // constructor reads the primary array and sets pointers to index elements
    function __construct($file_pointer)
    {
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($file_pointer, $size);
        $this->$primary_array = unpack("N*", $read_bytes);
        //secondary array
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $this->$secondary_array_start = $file_pointer;
        //TODO:load secondary array into memory
        fseek($file_pointer,$size,SEEK_CUR);
        //postings list
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $this->$postings_list_start = $file_pointer;
        fseek($file_pointer,$size,SEEK_CUR);
        // document map
        $this->$document_map_start = $file_pointer;
    }
}
function runQuery($stemmed_query_terms, $relevance_mesaure)
{

}
