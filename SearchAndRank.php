<?php
namespace cs267_hw5\search_program;

class SearchAndRank
{
    private $primary_array;
    private $secondary_array;
    private $postings_list_start;
    private $document_map_start;
    private $file_pointer;

    // constructor reads the primary array and sets pointers to index elements
    function __construct($file_pointer)
    {
        $curr_pointer = 0;
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($file_pointer, $size);
        $this->primary_array = unpack("N*", $read_bytes);
        $curr_pointer += 4 + $size;
        //secondary array
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($file_pointer, $size);
        $this->secondary_array = $read_bytes;
        $curr_pointer += 4 + $size;
        //postings list
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $this->postings_list_start = $curr_pointer + 4;
        // document map (does not have size prepended)
        $this->document_map_start = $curr_pointer + 4 + $size;
        $this->file_pointer = $file_pointer;
    }

    function runQuery($stemmed_query_terms, $relevance_mesaure)
    {
        $term = $stemmed_query_terms[0];
        $postings_offset = $this->binarySearch($term, 1, count($this->primary_array));
        echo $postings_offset . "\n";
        //print_r(fstat($this->file_pointer));
        echo fseek($this->file_pointer, $this->postings_list_start +
            $postings_offset, SEEK_SET);
        $read_bytes = fread($this->file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($this->file_pointer, $size);
        $encoded_postings = $read_bytes;
        $read_bytes = fread($this->file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($this->file_pointer, $size);
        $encoded_frequencies = $read_bytes;
        print($encoded_frequencies);
    }

    function binarySearch($term,$low, $high)
    {
        if ($this->get_term_from_dictionary($low) === $term) {
            $bytes = substr($this->secondary_array, $this->primary_array[$low] + 4
                + strlen($term), 4);
            return unpack("N", $bytes)[1];
        } else if ($this->get_term_from_dictionary($high) === $term) {
            $bytes = substr($this->secondary_array, $this->primary_array[$high] + 4
                + strlen($term), 4);
            return unpack("N", $bytes)[1];
        }
        $mid = ceil(($high + $low)/2);
        $mid_term = $this->get_term_from_dictionary($mid);
        if ($mid_term === $term) {
            $bytes = substr($this->secondary_array, $this->primary_array[$mid] + 4
                + strlen($term), 4);
            return unpack("N", $bytes)[1];
        } else if (strcmp($mid_term, $term) > 0) {
            return $this->binarySearch($term, $low, $mid-1);
        } else if (strcmp($mid_term, $term) < 0) {
            return $this->binarySearch($term, $mid+1, $high);
        }
    }

    function get_term_from_dictionary($index)
    {
        $offset = $this->primary_array[$index];
        $bytes = substr($this->secondary_array, $offset, 4);
        $len = unpack("N", $bytes)[1];
        return substr($this->secondary_array, $offset + 4, $len);
    }
}
