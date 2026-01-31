import { post } from "../utilities/ajax";

export default class StreamitInvoice {
    constructor() {
        this.setupEventHandlers();
    }

    setupEventHandlers() {
        // Use event delegation for download buttons
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (target.matches('.streamit-download-invoice') ||
                target.closest('.streamit-download-invoice')) {
                this.handleDownloadClick(event);
            }
        });
    }

    async handleDownloadClick(e) {
        e.preventDefault();

        const button = e.target.closest('.streamit-download-invoice');
        if (!button) return;

        const orderId = button.dataset.orderId;
        if (!orderId) {
            console.error('No order ID found');
            return;
        }

        // Set loading state
        this.setButtonLoadingState(button, true, 'Downloading...');

        try {
            const nonce = button.dataset.nonce || window.streamit_nonce || '';
            const response = await post('st_download_invoice', {
                order_id: orderId,
                nonce: nonce
            });

            if (!response.success) {
                throw new Error('Download failed');
            }

            const { pdf_base64, filename, content_type } = response.data;

            if (content_type !== 'application/pdf') {
                throw new Error('Invalid file type');
            }

            // Download the PDF
            await this.downloadPDF(pdf_base64, filename || 'invoice.pdf');

            // Reset button state after successful download
            setTimeout(() => {
                this.setButtonLoadingState(button, false);
            }, 1000);

        } catch (error) {
            console.error('Invoice download error:', error);

            // Reset button state on error
            setTimeout(() => {
                this.setButtonLoadingState(button, false);
            }, 1000);
        }
    }

    setButtonLoadingState(button, isLoading, loadingText = '') {
        if (isLoading) {
            button.disabled = true;
            button.setAttribute('data-original-text', button.textContent);
            button.textContent = loadingText;

            // Add loading class for styling if needed
            button.classList.add('loading');
        } else {
            button.disabled = false;
            button.textContent = button.getAttribute('data-original-text') || 'Download Invoice';
            button.classList.remove('loading');
        }
    }

    async downloadPDF(base64Data, filename) {
        return new Promise((resolve, reject) => {
            try {
                // Convert base64 to blob
                const byteCharacters = atob(base64Data);
                const byteNumbers = new Uint8Array(byteCharacters.length);

                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }

                const blob = new Blob([byteNumbers], { type: 'application/pdf' });

                // Create and trigger download
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');

                link.href = url;
                link.download = filename;
                link.style.display = 'none';

                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Clean up URL object
                setTimeout(() => {
                    URL.revokeObjectURL(url);
                }, 100);

                resolve();
            } catch (error) {
                reject(error);
            }
        });
    }

    // Alternative download method using fetch (if you have a direct URL)
    async downloadPDFViaURL(downloadUrl, filename) {
        try {
            const response = await fetch(downloadUrl);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);

            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Clean up
            setTimeout(() => {
                URL.revokeObjectURL(url);
            }, 100);

        } catch (error) {
            console.error('Download failed:', error);
            throw error;
        }
    }
}