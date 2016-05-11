<?php
namespace cs267_hw5\search_program;

use SplHeap;

class MinHeap extends SplHeap {
    public function compare($value1, $value2)
    {
        list($term1, $docId1) = each($value1);
        list($term2, $docId2) = each($value2);
        if ($docId1 === $docId2) {
            return 0;
        }
        return $docId1 < $docId2 ? 1 : -1;
    }
}

class SearchAndRank
{
    private $primary_array;
    private $secondary_array;
    private $postings_list_start;
    private $document_map_start;
    private $file_pointer;
    private $avg_doc_len;

    // constructor reads the dictionary in memory and sets pointers to index elements
    function __construct($file_pointer)
    {
        $read_bytes = fread($file_pointer, 4);
        $this->avg_doc_len = unpack("N", $read_bytes)[1];
        $curr_pointer = 4;
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($file_pointer, $size);
        $this->primary_array = unpack("N*", $read_bytes);
        $curr_pointer += 4 + $size;
        // secondary array
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $read_bytes = fread($file_pointer, $size);
        $this->secondary_array = $read_bytes;
        $curr_pointer += 4 + $size;
        // postings list
        $read_bytes = fread($file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $this->postings_list_start = $curr_pointer + 4;
        // document map
        $this->document_map_start = $curr_pointer + 4 + $size;
        $this->file_pointer = $file_pointer;
    }

    public function runQuery($stemmed_query_terms, $relevance_measure)
    {
        $result = [];
        if ($relevance_measure === "BM25") {
            $result = $this->get_relevant_docs_BM25($stemmed_query_terms);
        } else if ($relevance_measure === "DFR") {
            $result = $this->get_relevant_docs_DFR($stemmed_query_terms);
        }
        return $result;
    }

    private function get_relevant_docs_BM25($stemmed_query_terms)
    {
        $k = 20;
        $bm25Result = [];
        $resultHeap = new MinHeap();
        $docIdHeap = new MinHeap();
        $term_info_map = get_term_info_map($stemmed_query_terms);
        foreach ($stemmed_query_terms as $term) {
            if (!key_exists($term, $term_info_map)) {
                $term_info = get_term_info($term);
                $term_info_map[$term] = $term_info;
            }
            $term_info = $term_info_map[$term];
            if (!is_null($term_info)) {
                $docIdHeap->insert([$term => $doc_offset);
            }
        }
    }

    private function get_term_info($term)
    {
        $postings_offset = $this->binary_search($term, 1,
            count($this->primary_array));
        if ($postings_offset != -1) {
            fseek($this->file_pointer, $this->postings_list_start +
                $postings_offset, SEEK_SET);
            $read_bytes = fread($this->file_pointer, 4);
            $size = unpack("N", $read_bytes)[1];
            $encoded_postings = fread($this->file_pointer, $size);
            $read_bytes = fread($this->file_pointer, 4);
            $size = unpack("N", $read_bytes)[1];
            $encoded_frequencies = fread($this->file_pointer, $size);
            $delta_list = decode_gamma_code($encoded_postings);
            $posting_list = delta_to_posting($delta_list);
            $frequency_list = decode_gamma_code($encoded_frequencies);
            $term_info = array_combine($posting_list, $frequency_list);
            return $term_info;
        } else {
            return NULL;
        }
    }

    private function decode_gamma_code($encoded_string)
    {
        $binary_stream = charstream_to_binarystream($encoded_string);
        $list = [];
        $count=0;
        while ($i<strlen($binary_stream)) {
            if ($binary_stream[$i]==0) {
                $count+=1;
                $i+=1;
            } else {
                $list[] = bindec(substr($binary_stream,$i,$count+1)) - 1;
                $i+=$count+1;
                $count=0;
            }
        }
        return $list;
    }

    private function charstream_to_binarystream($encoded_string)
    {
        $binaryStream = "";
        $len = strlen($encoded_string);
        for ($i = 0; $i < $len; $i++ ) {
            $char = $encoded_string[$i];
            $binary = decbin(ord($char));
            $binary = str_pad($binary, 8, "0", STR_PAD_LEFT);
            $binaryStream = $binaryStream.$binary;
        }
        return $binaryStream;
    }

    private function delta_to_posting($delta_list)
    {
        for ($i = 1; i < count($delta_list); $i++) {
            $delta_list[i] = $delta_list[i-1] + $delta_list[i];
        }
        return $delta_list;
    }

    private function binary_search($term,$low, $high)
    {
        if ($low > $high) {
            return -1;
        }
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
            return $this->binary_search($term, $low, $mid-1);
        } else if (strcmp($mid_term, $term) < 0) {
            return $this->binary_search($term, $mid+1, $high);
        }
    }

    private function get_term_from_dictionary($index)
    {
        $offset = $this->primary_array[$index];
        $bytes = substr($this->secondary_array, $offset, 4);
        $len = unpack("N", $bytes)[1];
        return substr($this->secondary_array, $offset + 4, $len);
    }
}
