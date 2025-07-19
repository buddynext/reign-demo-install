/**
 * Reign Demo Install Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var ReignDemoInstall = {
        
        currentDemo: null,
        importInProgress: false,
        sessionCheckInterval: null,
        
        init: function() {
            this.bindEvents();
            this.loadDemos();
            this.startSessionMonitoring();
        },
        
        bindEvents: function() {
            // Demo search
            $('#reign-demo-search').on('keyup', this.debounce(this.searchDemos, 300));
            
            // Category filter
            $('#reign-demo-category').on('change', this.filterDemos.bind(this));
            
            // Import button
            $(document).on('click', '.reign-demo-import', this.showImportModal.bind(this));
            
            // Preview button
            $(document).on('click', '.reign-demo-preview-btn', this.previewDemo.bind(this));
            
            // Modal controls
            $('.reign-modal-close, .reign-cancel-import').on('click', this.hideImportModal.bind(this));
            $('.reign-start-import').on('click', this.startImport.bind(this));
            
            // Backup controls
            $('.reign-restore-backup').on('click', this.restoreBackup.bind(this));
            $('.reign-delete-backup').on('click', this.deleteBackup.bind(this));
            
            // Clean install checkbox
            $('#clean-install').on('change', this.toggleCleanInstallWarning.bind(this));
        },
        
        loadDemos: function() {
            var self = this;
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_get_demo_list',
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayDemos(response.data.demos);
                        self.populateCategories(response.data.categories);
                    } else {
                        self.showError('Failed to load demos');
                    }
                },
                error: function() {
                    self.showError('Network error while loading demos');
                }
            });
        },
        
        displayDemos: function(demos) {
            var $grid = $('#reign-demo-grid');
            $grid.empty();
            
            if (demos.length === 0) {
                $grid.html('<div class="reign-no-results">No demos found</div>');
                return;
            }
            
            demos.forEach(function(demo) {
                var $card = $('<div class="reign-demo-card" data-demo-id="' + demo.id + '">');
                
                var html = '<div class="reign-demo-preview">';
                html += '<img src="' + demo.thumbnail + '" alt="' + demo.name + '" />';
                html += '</div>';
                html += '<div class="reign-demo-info">';
                html += '<h3>' + demo.name + '</h3>';
                html += '<p>' + demo.description + '</p>';
                
                if (demo.tags && demo.tags.length > 0) {
                    html += '<div class="reign-demo-meta">';
                    html += '<span>' + demo.tags.join(', ') + '</span>';
                    html += '</div>';
                }
                
                html += '<div class="reign-demo-actions">';
                html += '<button class="button reign-demo-preview-btn" data-url="' + demo.preview_url + '">Preview</button>';
                html += '<button class="button button-primary reign-demo-import">Import</button>';
                html += '</div>';
                html += '</div>';
                
                $card.html(html);
                $grid.append($card);
            });
        },
        
        populateCategories: function(categories) {
            var $select = $('#reign-demo-category');
            
            $.each(categories, function(key, label) {
                if (key !== 'all') {
                    $select.append('<option value="' + key + '">' + label + '</option>');
                }
            });
        },
        
        searchDemos: function() {
            var keyword = $('#reign-demo-search').val();
            
            if (keyword.length < 2 && keyword.length > 0) {
                return;
            }
            
            ReignDemoInstall.loadDemosBySearch(keyword);
        },
        
        filterDemos: function() {
            var category = $('#reign-demo-category').val();
            this.loadDemosByCategory(category);
        },
        
        loadDemosBySearch: function(keyword) {
            var self = this;
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_get_demo_list',
                    search: keyword,
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayDemos(response.data.demos);
                    }
                }
            });
        },
        
        loadDemosByCategory: function(category) {
            var self = this;
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_get_demo_list',
                    category: category,
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayDemos(response.data.demos);
                    }
                }
            });
        },
        
        showImportModal: function(e) {
            e.preventDefault();
            
            var $card = $(e.target).closest('.reign-demo-card');
            this.currentDemo = {
                id: $card.data('demo-id'),
                name: $card.find('h3').text()
            };
            
            $('#reign-demo-import-modal').fadeIn();
        },
        
        hideImportModal: function(e) {
            e.preventDefault();
            
            if (this.importInProgress) {
                if (!confirm('Import is in progress. Are you sure you want to close?')) {
                    return;
                }
            }
            
            $('#reign-demo-import-modal').fadeOut();
        },
        
        previewDemo: function(e) {
            e.preventDefault();
            var url = $(e.target).data('url');
            window.open(url, '_blank');
        },
        
        startImport: function(e) {
            e.preventDefault();
            
            if (!this.currentDemo) {
                return;
            }
            
            var confirmMsg = reign_demo_install.messages.confirm_import;
            if (!confirm(confirmMsg)) {
                return;
            }
            
            this.importInProgress = true;
            $('.reign-import-options').hide();
            $('.reign-import-progress').show();
            $('.reign-start-import').prop('disabled', true);
            
            this.updateProgress(0, 'Preparing import...');
            
            // Start with user preservation
            this.preserveUser();
        },
        
        preserveUser: function() {
            var self = this;
            
            this.updateProgress(5, 'Preserving admin user...');
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_preserve_user',
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.addLog('Admin user preserved: ' + response.data.user_data.login, 'success');
                        self.processImportStep('backup');
                    } else {
                        self.handleImportError(response.data.message);
                    }
                },
                error: function() {
                    self.handleImportError('Failed to preserve user');
                }
            });
        },
        
        processImportStep: function(step) {
            var self = this;
            var stepProgress = {
                'backup': 10,
                'download': 20,
                'plugins': 40,
                'content': 60,
                'files': 80,
                'settings': 90,
                'cleanup': 100
            };
            
            var stepMessages = {
                'backup': 'Creating backup...',
                'download': 'Downloading demo files...',
                'plugins': 'Installing required plugins...',
                'content': 'Importing demo content...',
                'files': 'Importing media files...',
                'settings': 'Importing settings...',
                'cleanup': 'Finalizing import...'
            };
            
            this.updateProgress(stepProgress[step], stepMessages[step]);
            
            var options = {
                import_content: $('#import-content').is(':checked'),
                import_media: $('#import-media').is(':checked'),
                import_users: $('#import-users').is(':checked'),
                import_settings: $('#import-settings').is(':checked'),
                clean_existing: $('#clean-install').is(':checked'),
                backup_database: $('#backup-before-import').is(':checked')
            };
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_import_step',
                    demo_id: this.currentDemo.id,
                    step: step,
                    options: options,
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.addLog(response.data.message, 'success');
                        
                        if (response.data.next_step) {
                            setTimeout(function() {
                                self.processImportStep(response.data.next_step);
                            }, 1000);
                        } else if (response.data.redirect_url) {
                            self.completeImport(response.data.redirect_url);
                        }
                    } else {
                        self.handleImportError(response.data.message);
                    }
                },
                error: function() {
                    self.handleImportError('Network error during ' + step);
                }
            });
        },
        
        updateProgress: function(percent, status) {
            $('.reign-progress-fill').css('width', percent + '%');
            $('.reign-progress-status').text(status);
        },
        
        addLog: function(message, type) {
            var $log = $('.reign-progress-log');
            var timestamp = new Date().toLocaleTimeString();
            var $entry = $('<div>').addClass(type).text('[' + timestamp + '] ' + message);
            $log.append($entry);
            $log.scrollTop($log[0].scrollHeight);
        },
        
        handleImportError: function(message) {
            this.importInProgress = false;
            this.addLog('Error: ' + message, 'error');
            $('.reign-start-import').prop('disabled', false).text('Retry Import');
            alert(reign_demo_install.messages.import_failed + '\n\n' + message);
        },
        
        completeImport: function(redirectUrl) {
            this.importInProgress = false;
            this.updateProgress(100, 'Import completed!');
            this.addLog('Import completed successfully!', 'success');
            
            setTimeout(function() {
                alert(reign_demo_install.messages.import_complete);
                window.location.href = redirectUrl;
            }, 2000);
        },
        
        startSessionMonitoring: function() {
            var self = this;
            
            this.sessionCheckInterval = setInterval(function() {
                if (self.importInProgress) {
                    self.checkSession();
                }
            }, 30000); // Check every 30 seconds
        },
        
        checkSession: function() {
            var self = this;
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_check_session',
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var status = response.data.status;
                        
                        if (status !== 'active' && status !== 'import_complete') {
                            self.addLog('Session issue detected: ' + status, 'warning');
                            
                            if (response.data.can_restore) {
                                self.restoreSession();
                            }
                        }
                    }
                }
            });
        },
        
        restoreSession: function() {
            var self = this;
            
            this.addLog('Attempting to restore session...', 'warning');
            
            $.ajax({
                url: reign_demo_install.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_restore_session',
                    nonce: reign_demo_install.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.addLog('Session restored successfully', 'success');
                    } else {
                        self.addLog('Failed to restore session', 'error');
                    }
                }
            });
        },
        
        toggleCleanInstallWarning: function() {
            if ($('#clean-install').is(':checked')) {
                if (!confirm('Warning: Clean install will delete all existing content except your admin user. Are you sure?')) {
                    $('#clean-install').prop('checked', false);
                }
            }
        },
        
        restoreBackup: function(e) {
            e.preventDefault();
            
            var backupId = $(e.target).data('backup-id');
            
            if (!confirm('Are you sure you want to restore this backup? This will replace your current site content.')) {
                return;
            }
            
            // Implementation for backup restoration
            alert('Backup restoration will be implemented');
        },
        
        deleteBackup: function(e) {
            e.preventDefault();
            
            var backupId = $(e.target).data('backup-id');
            
            if (!confirm('Are you sure you want to delete this backup?')) {
                return;
            }
            
            // Implementation for backup deletion
            alert('Backup deletion will be implemented');
        },
        
        showError: function(message) {
            var $grid = $('#reign-demo-grid');
            $grid.html('<div class="reign-no-results">Error: ' + message + '</div>');
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize
    ReignDemoInstall.init();
});