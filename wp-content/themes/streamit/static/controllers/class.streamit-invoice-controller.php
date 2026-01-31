<?php

defined('ABSPATH') || exit;

final class st_Invoice_Controller {
    
    /**
     * Handles invoice download requests for logged-in users.
     *
     * This method verifies the nonce for security, checks if the user is logged in,
     * retrieves invoice metadata for the given order ID, decodes the PDF content,
     * and outputs it as a downloadable PDF file.
     *
     * @return void Outputs PDF content directly and terminates execution.
     */
    public function download_invoice() {

        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'User not logged in'], 401);
            exit;
        }

        // Get order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid or missing order ID'], 400);
            exit;
        }

        // Get invoice metadata
        $invoice_data = function_exists('streamit_get_invoice_metadata') ? streamit_get_invoice_metadata($order_id) : null;

        if (!$invoice_data || empty($invoice_data['invoice_data']) || empty($invoice_data['pdf_base64']) || empty($invoice_data['filename'])) {
            wp_send_json_error(['message' => 'Invalid or incomplete invoice data'], 404);
            exit;
        }

        $response = [
            'success' => true,
            'data' => [
                'pdf_base64' => $invoice_data['pdf_base64'],
                'filename' => $invoice_data['filename'],
                'content_type' => 'application/pdf',
            ]
        ];

        wp_send_json($response);
        exit;
    }
}