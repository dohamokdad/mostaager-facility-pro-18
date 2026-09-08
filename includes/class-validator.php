<?php
/**
 * Data Validator - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Data_Validator {
    
    /**
     * Validate import data
     */
    public function validate_import_data($data) {
        $errors = array();
        $warnings = array();
        
        // Validate required fields
        $required_fields = array('building_title', 'unit_number');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf('الحقل المطلوب "%s" مفقود', $field);
            }
        }
        
        // Validate email if provided
        if (!empty($data['tenant_email']) && !is_email($data['tenant_email'])) {
            $errors[] = 'بريد المستأجر الإلكتروني غير صحيح';
        }
        
        // Validate numeric fields
        if (!empty($data['monthly_rent']) && !is_numeric($data['monthly_rent'])) {
            $warnings[] = 'الإيجار الشهري يجب أن يكون رقماً';
        }
        
        if (!empty($data['fave_property_price']) && !is_numeric($data['fave_property_price'])) {
            $warnings[] = 'سعر العقار يجب أن يكون رقماً';
        }
        
        // Validate dates
        if (!empty($data['lease_start_date']) && !$this->validate_date($data['lease_start_date'])) {
            $errors[] = 'تاريخ بداية العقد غير صحيح (استخدم YYYY-MM-DD)';
        }
        
        if (!empty($data['lease_end_date']) && !$this->validate_date($data['lease_end_date'])) {
            $errors[] = 'تاريخ نهاية العقد غير صحيح (استخدم YYYY-MM-DD)';
        }
        
        // Validate date logic
        if (!empty($data['lease_start_date']) && !empty($data['lease_end_date'])) {
            if (strtotime($data['lease_end_date']) <= strtotime($data['lease_start_date'])) {
                $errors[] = 'تاريخ نهاية العقد يجب أن يكون بعد تاريخ البداية';
            }
        }
        
        // Validate unit type
        if (!empty($data['ms_unit_type']) && !$this->validate_unit_type($data['ms_unit_type'])) {
            $warnings[] = 'نوع الوحدة غير معروف';
        }
        
        // Validate unit status
        if (!empty($data['ms_unit_status']) && !$this->validate_unit_status($data['ms_unit_status'])) {
            $warnings[] = 'حالة الوحدة غير معروفة';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => $this->sanitize_data($data)
        );
    }
    
    /**
     * Validate date format
     */
    private function validate_date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate unit type
     */
    private function validate_unit_type($type) {
        $valid_types = array('apartment', 'shop', 'office', 'villa', 'studio');
        return in_array($type, $valid_types);
    }
    
    /**
     * Validate unit status
     */
    private function validate_unit_status($status) {
        $valid_statuses = array('available', 'rented', 'sold', 'maintenance');
        return in_array($status, $valid_statuses);
    }
    
    /**
     * Sanitize data
     */
    private function sanitize_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $sanitized[$key] = floatval($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate file upload
     */
    public function validate_file_upload($file) {
        $errors = array();
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'لم يتم رفع الملف بشكل صحيح';
            return array('valid' => false, 'errors' => $errors);
        }
        
        // Check file size
        $max_size = $this->get_max_file_size();
        if ($file['size'] > $max_size) {
            $errors[] = sprintf('حجم الملف كبير جداً. الحد الأقصى: %s MB', $max_size / 1048576);
        }
        
        // Check file type
        $allowed_types = array('application/xml', 'text/xml', 'text/csv', 'application/json', 'text/plain');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'نوع الملف غير مدعوم. يرجى استخدام XML أو CSV أو JSON';
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get max file size
     */
    private function get_max_file_size() {
        $settings = get_option('ms_import_settings', array());
        $max_mb = isset($settings['max_file_size_mb']) ? intval($settings['max_file_size_mb']) : 10;
        return $max_mb * 1048576; // Convert to bytes
    }
}
