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

class MaxHeap extends SplHeap {
    public function compare($pair1, $pair2)
    {
        list($key1, $value1) = each($pair1);
        list($key2, $value2) = each($pair2);
        if ($value1 === $value2) {
            return 0;
        }
        return $value1 > $value2 ? 1 : -1;
    }
}

define("mu", 1000, true);
define("k1", 1.2, true);
define("b", 0.75, true);
//TODO: read only one doc entry at a time
//TODO: read only one posting list at a time
//TODO: process queries conjunctively
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
    /**
     * SearchAndRank constructor.
     * @param $file_pointer
     */
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

    /**
     * @param $stemmed_query_terms
     * @param $relevance_measure
     * @return array
     */
    public function runQuery($stemmed_query_terms, $relevance_measure)
    {
        $k = 10;
        $result = [];
        $result_heap = new MinHeap();
        $doc_offset_heap = new MaxHeap();
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
                $doc_len = 0;
                if (!key_exists($doc_offset, $doc_offset_map)) {
                    $doc_info = $this->read_doc_info($doc_offset);
                    $doc_offset_map[$doc_offset] = $doc_info[0];
                    $doc_len = $doc_info[1];
                }
                if ($relevance_measure === "BM25") {
                    $doc_count = count($term_info_map[$term]);
                    $score = $score + $this->bm25_score($this->no_of_docs, $doc_count, $freq_in_doc,
                            $doc_len, $this->avg_doc_len);
                } else if ($relevance_measure === "LMD") {
                    $score = $score + $this->lmd_score($query_freq_arr[$term], $freq_in_doc, $this->corpus_size,
                            $term_count_map[$term], $n, $doc_len, $this->avg_doc_len);
                } else if ($relevance_measure === "DFR") {
                    $score = $score + $this->dfr_score($query_freq_arr[$term], $freq_in_doc, $term_count_map[$term],
                            $doc_len, $this->avg_doc_len, $this->no_of_docs);
                }

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
            $result[$doc_offset_map[$doc_offset]] = $score;
        }
        arsort($result);
        return $result;
    }

    /**
     * @param $q_t
     * @param $f_td
     * @param $l_t
     * @param $l_d
     * @param $l_avg
     * @param $no_of_docs
     * @return mixed
     */
    private function dfr_score($q_t, $f_td, $l_t, $l_d, $l_avg, $no_of_docs)
    {
        $f_td_norm = $f_td * log(1 + ($l_avg / $l_d));
        $result = $q_t * (log(1 + ($l_t / $no_of_docs)) + $f_td_norm * log(1 + ($no_of_docs / $l_t))
            / $f_td_norm + 1);
        return $result;
    }

    /**
     * @param $q_t
     * @param $f_td
     * @param $l_C
     * @param $l_t
     * @param $n
     * @param $l_d
     * @param $l_avg
     * @return float
     */
    private function lmd_score($q_t, $f_td, $l_C, $l_t, $n, $l_d, $l_avg)
    {
        $f_td_norm = $f_td * log(1 + ($l_avg / $l_d));
        $result = $q_t * log(1 + ($f_td_norm / mu) * ($l_C / $l_t)) - $n * log(1 + ($l_d/mu));
        return $result;
    }

    /**
     * @param $corpus_size
     * @param $doc_count
     * @param $freq_in_doc
     * @param $doc_len
     * @param $avg_doc_len
     * @return float
     */
    private function bm25_score($corpus_size, $doc_count, $freq_in_doc, $doc_len, $avg_doc_len)
    {
        $idf = $this->calculate_idf($corpus_size, $doc_count);
        $tf = ($freq_in_doc * (k1 + 1))/
              ($freq_in_doc + k1 *
              ((1 - b) + b * ($doc_len/$avg_doc_len)));
        $result = $idf * $tf;
        return $result;
    }

    /**
     * @param $corpus_size
     * @param $doc_count
     * @return float
     */
    function calculate_idf($corpus_size, $doc_count)
    {
        $result = 0.0;
        if ($doc_count != 0) {
            $result = log(($corpus_size/$doc_count),2);
        }
        return $result;
    }

    /**
     * @param $doc_offset
     * @return array
     */
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

    /**
     * @param $term
     * @return array|null
     */
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

    /**
     * @param $encoded_string
     * @return array
     */
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

    /**
     * @param $encoded_string
     * @return string
     */
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

    /**
     * @param $delta_list
     * @return mixed
     */
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

    /**
     * @param $term
     * @param $low
     * @param $high
     * @return int
     */
    private function binary_search($term, $low, $high)
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

    /**
     * @param $index
     * @return string
     */
    private function get_term_from_dictionary($index)
    {
        $offset = $this->primary_array[$index];
        $bytes = substr($this->secondary_array, $offset, 4);
        $len = unpack("N", $bytes)[1];
        return substr($this->secondary_array, $offset + 4, $len);
    }
}
