<?php
/**
 * Automation Importer - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Automation_Importer {
    
    /**
     * Import from string
     */
    public function import_from_string($file_content, $template_id) {
        $result = array('success' => false, 'imported' => 0, 'errors' => array());
        
        // Detect format
        $format = $this->detect_format($file_content);
        
        if (!$format) {
            $result['errors'][] = 'صيغة الملف غير مدعومة';
            return $result;
        }
        
        // Parse data
        $data = $this->parse_data($file_content, $format);
        
        if (!$data) {
            $result['errors'][] = 'فشل تحليل البيانات';
            return $result;
        }
        
        // Import data
        foreach ($data as $record) {
            $import_result = $this->import_single_record($record, $template_id);
            
            if ($import_result['success']) {
                $result['imported']++;
            } else {
                $result['errors'] = array_merge($result['errors'], $import_result['errors']);
            }
        }
        
        $result['success'] = $result['imported'] > 0;
        
        return $result;
    }
    
    /**
     * Detect format
     */
    private function detect_format($content) {
        if (strpos($content, '<?xml') !== false) {
            return 'xml';
        } elseif (strpos($content, '{') === 0) {
            return 'json';
        } elseif (strpos($content, ',') !== false) {
            return 'csv';
        }
        
        return false;
    }
    
    /**
     * Parse data
     */
    private function parse_data($content, $format) {
        switch ($format) {
            case 'xml':
                return $this->parse_xml($content);
            case 'json':
                return $this->parse_json($content);
            case 'csv':
                return $this->parse_csv($content);
            default:
                return false;
        }
    }
    
    /**
     * Parse XML
     */
    private function parse_xml($content) {
        $xml = simplexml_load_string($content);
        
        if ($xml === false) {
            return false;
        }
        
        return json_decode(json_encode($xml), true);
    }
    
    /**
     * Parse JSON
     */
    private function parse_json($content) {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Parse CSV
     */
    private function parse_csv($content) {
        $lines = explode("\n", $content);
        $headers = str_getcsv($lines[0]);
        $data = array();
        
        for ($i = 1; $i < count($lines); $i++) {
            if (empty(trim($lines[$i]))) {
                continue;
            }
            
            $values = str_getcsv($lines[$i]);
            $row = array();
            
            foreach ($headers as $index => $header) {
                $row[$header] = isset($values[$index]) ? $values[$index] : '';
            }
            
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * Import single record
     */
    private function import_single_record($record, $template_id) {
        $result = array('success' => false, 'errors' => array());
        
        // Validate data
        $validator = new MS_Data_Validator();
        $validation_result = $validator->validate_import_data($record);
        
        if (!$validation_result['valid']) {
            $result['errors'] = $validation_result['errors'];
            return $result;
        }
        
        // Apply template
        $templates = new MS_Templates_Manager();
        $template_result = $templates->apply_template($template_id, $record);
        
        if (!$template_result['success']) {
            $result['errors'][] = $template_result['error'];
            return $result;
        }
        
        // Create property
        $post_id = wp_insert_post(array(
            'post_type' => 'property',
            'post_title' => $record['title'] ?? 'Property ' . uniqid(),
            'post_content' => $record['description'] ?? '',
            'post_status' => 'publish'
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            // Add meta data
            foreach ($template_result['mapped_data'] as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
            
            $result['success'] = true;
        } else {
            $result['errors'][] = 'فشل إنشاء العقار';
        }
        
        return $result;
    }
}
