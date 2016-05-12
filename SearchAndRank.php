<?php
namespace cs267_hw5\search_program;

use SplHeap;

class MinHeap extends SplHeap {
    public function compare($pair1, $pair2)
    {
        list($key1, $value1) = each($pair1);
        list($key2, $value2) = each($pair2);
        if ($value1 === $value2) {
            return 0;
        }
        return $value1 < $value2 ? 1 : -1;
    }
}

define("mu", 1000, true);

class SearchAndRank
{
    private $primary_array;
    private $secondary_array;
    private $postings_list_start;
    private $document_map_start;
    private $file_pointer;
    private $no_of_docs;
    private $corpus_size;
    private $avg_doc_len;
    // reads the dictionary in memory and sets pointers to index elements
    function __construct($file_pointer)
    {
        $read_bytes = fread($file_pointer, 4);
        $this->no_of_docs = unpack("N", $read_bytes)[1];
        $read_bytes = fread($file_pointer, 4);
        $this->corpus_size = unpack("N", $read_bytes)[1];
        $this->avg_doc_len = $this->corpus_size/$this->no_of_docs;
        $curr_pointer = 8;
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
            $result = $this->get_relevant_docs_bm25($stemmed_query_terms);
        } else if ($relevance_measure === "LMD") {
            $result = $this->get_relevant_docs_lmd($stemmed_query_terms);
        }
        return $result;
    }

    private function get_relevant_docs_lmd($stemmed_query_terms)
    {
        $k = 20;
        $lmd_result = [];
        $result_heap = new MinHeap();
        $doc_offset_heap = new MinHeap();
        $term_info_map = [];
        foreach ($stemmed_query_terms as $term) {
            if (!key_exists($term, $term_info_map)) {
                $term_info = $this->get_term_info($term);
                $term_info_map[$term] = $term_info;
            }
            if (!is_null($term_info_map[$term])) {
                $doc_offset_heap->insert([$term => key($term_info_map[$term])]);
                next($term_info_map[$term]);
            }
        }
        $doc_offset_map = [];
        $term_count_map = [];
        $query_freq_arr = array_count_values($stemmed_query_terms);
        $n = count($stemmed_query_terms);
        while (each($doc_offset_heap->top())[1] != INF) {
            $score = 0;
            $doc_offset = each($doc_offset_heap->top())[1];
            // maps offset to doc_id and doc_len doc_offset => [doc_id, doc_len]
            while (each($doc_offset_heap->top())[1] == $doc_offset) {
                $term = each($doc_offset_heap->extract())[0];
                $freq_in_doc = $term_info_map[$term][$doc_offset];
                if (!key_exists($term, $term_count_map)) {
                    $frequencies = array_values($term_info_map[$term]);
                    $term_count_map[$term] = array_sum($frequencies);
                }
                if (!key_exists($doc_offset, $doc_offset_map)) {
                    $doc_info = $this->read_doc_info($doc_offset);
                    $doc_offset_map[$doc_offset] = $doc_info;
                }
                $doc_len = $doc_offset_map[$doc_offset][1];
                $score = $score + $this->lmd_score($query_freq_arr[$term], $freq_in_doc, $this->corpus_size,
                        $term_count_map[$term], $n, $doc_len, $this->avg_doc_len);
                $next_doc_offset = INF;
                if (key($term_info_map[$term]) != NULL) {
                    $next_doc_offset = key($term_info_map[$term]);
                }
                $doc_offset_heap->insert([$term => $next_doc_offset]);
                next($term_info_map[$term]);
            }
            if ($result_heap->count() < $k) {
                $result_heap->insert([$doc_offset => $score]);
            } else if ($score > each($result_heap->top())[1]) {
                $result_heap->extract();
                $result_heap->insert([$doc_offset => $score]);
            }
        }
        while($result_heap->valid()) {
            list($doc_offset, $score) = each($result_heap->extract());
            $lmd_result[$doc_offset_map[$doc_offset][0]] = $score;
        }
        arsort($lmd_result);
        return $lmd_result;
    }

    private function lmd_score($q_t, $f_td, $l_C, $l_t, $n, $l_d, $l_avg)
    {
        $f_td_norm = $f_td * log(1 + ($l_avg / $l_d));
        $result = $q_t * log(1 + ($f_td_norm / mu) * ($l_C / $l_t)) - $n * log(1 + $l_d/mu);
        return $result;
    }

    private function get_relevant_docs_bm25($stemmed_query_terms)
    {
        $k = 20;
        $bm25_result = [];
        $result_heap = new MinHeap();
        $doc_offset_heap = new MinHeap();
        $term_info_map = [];
        foreach ($stemmed_query_terms as $term) {
            if (!key_exists($term, $term_info_map)) {
                $term_info = $this->get_term_info($term);
                $term_info_map[$term] = $term_info;
            }
            if (!is_null($term_info_map[$term])) {
                $doc_offset_heap->insert([$term => key($term_info_map[$term])]);
                next($term_info_map[$term]);
            }
        }
        $doc_offset_map = [];
        while (each($doc_offset_heap->top())[1] != INF) {
            $score = 0;
            $doc_offset = each($doc_offset_heap->top())[1];
            // maps offset to doc_id and doc_len doc_offset => [doc_id, doc_len]
            while (each($doc_offset_heap->top())[1] == $doc_offset) {
                $term = each($doc_offset_heap->extract())[0];
                $doc_count = count($term_info_map[$term]);
                $freq_in_doc = $term_info_map[$term][$doc_offset];
                if (!key_exists($doc_offset, $doc_offset_map)) {
                    $doc_info = $this->read_doc_info($doc_offset);
                    $doc_offset_map[$doc_offset] = $doc_info;
                }
                $doc_len = $doc_offset_map[$doc_offset][1];
                $score = $score + $this->bm25_score($this->no_of_docs, $doc_count, $freq_in_doc,
                        $doc_len, $this->avg_doc_len);
                $next_doc_offset = INF;
                if (key($term_info_map[$term]) != NULL) {
                    $next_doc_offset = key($term_info_map[$term]);
                }
                $doc_offset_heap->insert([$term => $next_doc_offset]);
                next($term_info_map[$term]);
            }
            if ($result_heap->count() < $k) {
                $result_heap->insert([$doc_offset => $score]);
            } else if ($score > each($result_heap->top())[1]) {
                $result_heap->extract();
                $result_heap->insert([$doc_offset => $score]);
            }
        }
        while($result_heap->valid()) {
            list($doc_offset, $score) = each($result_heap->extract());
            $bm25_result[$doc_offset_map[$doc_offset][0]] = $score;
        }
        arsort($bm25_result);
        return $bm25_result;
    }

    /*
    b = 0.75
    k1 = 1.2
    */
    private function bm25_score($corpus_size, $doc_count, $freq_in_doc, $doc_len, $avg_doc_len)
    {
        $idf = $this->calculate_idf($corpus_size, $doc_count);
        $tf = ($freq_in_doc * (1.2 + 1))/
              ($freq_in_doc + 1.2 *
              ((1 - 0.75) + 0.75 * ($doc_len/$avg_doc_len)));
        $result = $idf * $tf;
        return $result;
    }

    function calculate_idf($corpus_size, $doc_count)
    {
        $result = 0.0;
        if ($doc_count != 0) {
            $result = log(($corpus_size/$doc_count),2);
        }
        return $result;
    }

    private function read_doc_info($doc_offset)
    {
        fseek($this->file_pointer, $this->document_map_start + $doc_offset, SEEK_SET);
        $read_bytes = fread($this->file_pointer, 4);
        $size = unpack("N", $read_bytes)[1];
        $doc_id = fread($this->file_pointer, $size);
        $read_bytes = fread($this->file_pointer, 4);
        $doc_len = unpack("N", $read_bytes)[1];
        return [$doc_id, $doc_len];
    }

    private function get_term_info($term)
    {
        $postings_offset = $this->binary_search($term, 1,
            count($this->primary_array));
        if ($postings_offset != -1) {
            // setting file pointer to the position of posting list
            fseek($this->file_pointer, $this->postings_list_start + $postings_offset, SEEK_SET);
            $read_bytes = fread($this->file_pointer, 4);
            $size = unpack("N", $read_bytes)[1];
            $encoded_postings = fread($this->file_pointer, $size);
            $read_bytes = fread($this->file_pointer, 4);
            $size = unpack("N", $read_bytes)[1];
            $encoded_frequencies = fread($this->file_pointer, $size);
            $delta_list = $this->decode_gamma_code($encoded_postings);
            $posting_list = $this->delta_to_posting($delta_list);
            $frequency_list = $this->decode_gamma_code($encoded_frequencies);
            $term_info = array_combine($posting_list, $frequency_list);
            return $term_info;
        } else {
            return NULL;
        }
    }

    private function decode_gamma_code($encoded_string)
    {
        $binary_stream = $this->charstream_to_binarystream($encoded_string);
        $list = [];
        $count = 0;
        $i = 0;
        while ($i < strlen($binary_stream)) {
            if ($binary_stream[$i] == 0) {
                $count += 1;
                $i += 1;
            } else {
                $list[] = bindec(substr($binary_stream, $i, $count + 1)) - 1;
                $i += $count + 1;
                $count = 0;
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
        $len = count($delta_list);
        if ($len > 1) {
            for ($i = 1; $i < $len; $i++) {
                $delta_list[$i] = $delta_list[$i-1] + $delta_list[$i];
            }
        }
        return $delta_list;
    }

    private function binary_search($term,$low, $high)
    {
        if ($low > $high) {
            return -1;
        }
        if ($this->get_term_from_dictionary($low) === $term) {
            $bytes = substr($this->secondary_array, $this->primary_array[$low] + 4 + strlen($term), 4);
            return unpack("N", $bytes)[1];
        } else if ($this->get_term_from_dictionary($high) === $term) {
            $bytes = substr($this->secondary_array, $this->primary_array[$high] + 4 + strlen($term), 4);
            return unpack("N", $bytes)[1];
        }
        $mid = (int) ceil(($high + $low)/2);
        $mid_term = $this->get_term_from_dictionary($mid);
        if ($mid_term === $term) {
            $bytes = substr($this->secondary_array, $this->primary_array[$mid] + 4 + strlen($term), 4);
            return unpack("N", $bytes)[1];
        } else if (strcmp($mid_term, $term) > 0) {
            return $this->binary_search($term, $low, $mid-1);
        } else if (strcmp($mid_term, $term) < 0) {
            return $this->binary_search($term, $mid+1, $high);
        } else {
            return -1;
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
