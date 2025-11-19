( function( $ ) {
    'use strict';
    
    /**
     * SCD Debug Console
     * 
     * Provides an interactive debug console for real-time plugin debugging.
     */
    var SCD_Debug_Console_Manager = {
        
        /**
         * Initialize the debug console.
         */
        init: function() {
            if ( ! window.SCD_Debug_Console || ! window.SCD_Debug_Console.enabled ) {
                return;
            }
            
            this.bindEvents();
            this.loadInitialLogs();
            this.startAutoRefresh();
        },
        
        /**
         * Bind console events.
         */
        bindEvents: function() {
            var self = this;
            
            // Toggle console visibility
            $( '#scd-debug-toggle' ).on( 'click', function() {
                $( '.scd-debug-console-content' ).toggle();
            } );
            
            // Tab switching
            $( '.scd-debug-tab' ).on( 'click', function() {
                var tab = $( this ).data( 'tab' );
                self.switchTab( tab );
            } );
            
            // Clear console
            $( '#scd-debug-clear' ).on( 'click', function() {
                self.clearLogs();
            } );
            
            // Refresh logs
            $( '#scd-debug-refresh' ).on( 'click', function() {
                self.loadLogs();
            } );
            
            // Execute code
            $( '#scd-debug-execute' ).on( 'click', function() {
                self.executeCode();
            } );
            
            // Inspect variable
            $( '#scd-debug-inspect' ).on( 'click', function() {
                self.inspectVariable();
            } );
            
            // Log lines selector
            $( '#scd-debug-log-lines' ).on( 'change', function() {
                self.loadLogs();
            } );
            
            // Keyboard shortcuts
            $( document ).on( 'keydown', function( e ) {
                // Ctrl+Shift+D to toggle console
                if ( e.ctrlKey && e.shiftKey && e.keyCode === 68 ) {
                    e.preventDefault();
                    $( '#scd-debug-toggle' ).click();
                }
                
                // Ctrl+Enter to execute code
                if ( e.ctrlKey && e.keyCode === 13 && $( e.target ).is( '#scd-debug-code-input' ) ) {
                    e.preventDefault();
                    self.executeCode();
                }
            } );
        },
        
        /**
         * Switch console tab.
         * 
         * @param {string} tabName Tab name to switch to.
         */
        switchTab: function( tabName ) {
            $( '.scd-debug-tab' ).removeClass( 'active' );
            $( '.scd-debug-panel' ).hide();
            
            $( '.scd-debug-tab[data-tab="' + tabName + '"]' ).addClass( 'active' );
            $( '#scd-debug-' + tabName ).show();
        },
        
        /**
         * Load initial logs.
         */
        loadInitialLogs: function() {
            this.loadLogs();
            
            // Display buffered messages
            if ( window.SCD_Debug_Console.buffer && window.SCD_Debug_Console.buffer.length > 0 ) {
                this.displayBufferedMessages();
            }
        },
        
        /**
         * Display buffered messages.
         */
        displayBufferedMessages: function() {
            var output = $( '#scd-debug-log-output' );
            var buffer = window.SCD_Debug_Console.buffer;
            
            for ( var i = 0; i < buffer.length; i++ ) {
                var msg = buffer[i];
                var timestamp = new Date( msg.timestamp * 1000 ).toLocaleTimeString();
                var line = '[' + timestamp + '] [BUFFER] SCD.' + msg.type.toUpperCase() + ': ' + msg.message + '\n';
                output.append( line );
            }
            
            output.scrollTop( output[0].scrollHeight );
        },
        
        /**
         * Load debug logs from server.
         */
        loadLogs: function() {
            var self = this;
            var lines = $( '#scd-debug-log-lines' ).val() || 50;
            
            $.post( window.SCD_Debug_Console.ajax_url, {
                action: 'scd_debug_console',
                console_action: 'get_logs',
                nonce: window.SCD_Debug_Console.nonce,
                lines: lines
            } )
            .done( function( response ) {
                if ( response.success ) {
                    $( '#scd-debug-log-output' ).text( response.data.logs );
                    self.scrollToBottom( '#scd-debug-log-output' );
                } else {
                    self.showError( 'Failed to load logs: ' + response.data );
                }
            } )
            .fail( function() {
                self.showError( 'AJAX request failed' );
            } );
        },
        
        /**
         * Clear debug logs.
         */
        clearLogs: function() {
            var self = this;
            
            if ( ! confirm( 'Are you sure you want to clear all debug logs?' ) ) {
                return;
            }
            
            $.post( window.SCD_Debug_Console.ajax_url, {
                action: 'scd_debug_console',
                console_action: 'clear_logs',
                nonce: window.SCD_Debug_Console.nonce
            } )
            .done( function( response ) {
                if ( response.success ) {
                    $( '#scd-debug-log-output' ).empty();
                    self.showSuccess( 'Debug logs cleared' );
                } else {
                    self.showError( 'Failed to clear logs: ' + response.data );
                }
            } );
        },
        
        /**
         * Execute debug code.
         */
        executeCode: function() {
            var self = this;
            var code = $( '#scd-debug-code-input' ).val();
            
            if ( ! code.trim() ) {
                self.showError( 'Please enter some code to execute' );
                return;
            }
            
            var output = $( '#scd-debug-console-output' );
            output.append( '> ' + code + '\n' );
            
            $.post( window.SCD_Debug_Console.ajax_url, {
                action: 'scd_debug_console',
                console_action: 'execute_code',
                nonce: window.SCD_Debug_Console.nonce,
                code: code
            } )
            .done( function( response ) {
                if ( response.success ) {
                    var data = response.data;
                    if ( data.executed ) {
                        if ( data.output ) {
                            output.append( data.output + '\n' );
                        }
                        if ( data.error ) {
                            output.append( 'ERROR: ' + data.error + '\n' );
                        }
                    } else {
                        output.append( 'ERROR: ' + ( data.error || 'Code not executed' ) + '\n' );
                    }
                } else {
                    output.append( 'AJAX ERROR: ' + response.data + '\n' );
                }
                
                output.append( '\n' );
                self.scrollToBottom( '#scd-debug-console-output' );
            } );
            
            // Clear input
            $( '#scd-debug-code-input' ).val( '' );
        },
        
        /**
         * Inspect variable.
         */
        inspectVariable: function() {
            var self = this;
            var varName = $( '#scd-debug-inspect-var' ).val();
            
            if ( ! varName ) {
                self.showError( 'Please select a variable to inspect' );
                return;
            }
            
            var output = $( '#scd-debug-inspector-output' );
            output.append( 'Inspecting: ' + varName + '\n\n' );
            
            $.post( window.SCD_Debug_Console.ajax_url, {
                action: 'scd_debug_console',
                console_action: 'inspect_variable',
                nonce: window.SCD_Debug_Console.nonce,
                variable: varName
            } )
            .done( function( response ) {
                if ( response.success ) {
                    var data = JSON.stringify( response.data.data, null, 2 );
                    output.append( data + '\n\n' );
                } else {
                    output.append( 'ERROR: ' + response.data + '\n\n' );
                }
                
                self.scrollToBottom( '#scd-debug-inspector-output' );
            } );
        },
        
        /**
         * Start auto-refresh for logs.
         */
        startAutoRefresh: function() {
            var self = this;
            
            // Auto-refresh logs every 10 seconds if logs tab is active
            setInterval( function() {
                if ( $( '.scd-debug-tab[data-tab="logs"]' ).hasClass( 'active' ) ) {
                    self.loadLogs();
                }
            }, 10000 );
        },
        
        /**
         * Scroll element to bottom.
         * 
         * @param {string} selector Element selector.
         */
        scrollToBottom: function( selector ) {
            var element = $( selector )[0];
            if ( element ) {
                element.scrollTop = element.scrollHeight;
            }
        },
        
        /**
         * Show success message.
         * 
         * @param {string} message Success message.
         */
        showSuccess: function( message ) {
            this.showNotice( message, 'success' );
        },
        
        /**
         * Show error message.
         * 
         * @param {string} message Error message.
         */
        showError: function( message ) {
            this.showNotice( message, 'error' );
        },
        
        /**
         * Show notice message.
         * 
         * @param {string} message Notice message.
         * @param {string} type Notice type.
         */
        showNotice: function( message, type ) {
            var notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );
            $( '.scd-debug-console-header' ).after( notice );
            
            setTimeout( function() {
                notice.fadeOut( function() {
                    notice.remove();
                } );
            }, 3000 );
        }
    };
    
    // Public API for external use
    window.SCD_Debug_Console_API = {
        
        /**
         * Log debug message.
         * 
         * @param {string} message Debug message.
         * @param {Object} context Optional context.
         */
        debug: function( message, context ) {
            console.log( '[SCD Debug]', message, context || {} );
            this.addToConsole( message, 'debug', context );
        },
        
        /**
         * Log info message.
         * 
         * @param {string} message Info message.
         * @param {Object} context Optional context.
         */
        info: function( message, context ) {
            console.info( '[SCD Info]', message, context || {} );
            this.addToConsole( message, 'info', context );
        },
        
        /**
         * Log warning message.
         * 
         * @param {string} message Warning message.
         * @param {Object} context Optional context.
         */
        warn: function( message, context ) {
            console.warn( '[SCD Warning]', message, context || {} );
            this.addToConsole( message, 'warning', context );
        },
        
        /**
         * Log error message.
         * 
         * @param {string} message Error message.
         * @param {Object} context Optional context.
         */
        error: function( message, context ) {
            console.error( '[SCD Error]', message, context || {} );
            this.addToConsole( message, 'error', context );
        },
        
        /**
         * Add message to debug console.
         * 
         * @param {string} message Log message.
         * @param {string} type Message type.
         * @param {Object} context Optional context.
         */
        addToConsole: function( message, type, context ) {
            if ( ! window.SCD_Debug_Console || ! window.SCD_Debug_Console.enabled ) {
                return;
            }
            
            var timestamp = new Date().toLocaleTimeString();
            var line = '[' + timestamp + '] [JS] SCD.' + type.toUpperCase() + ': ' + message;
            
            if ( context && Object.keys( context ).length > 0 ) {
                line += ' | ' + JSON.stringify( context );
            }
            
            var output = $( '#scd-debug-log-output' );
            if ( output.length ) {
                output.append( line + '\n' );
                SCD_Debug_Console_Manager.scrollToBottom( '#scd-debug-log-output' );
            }
        },
        
        /**
         * Inspect JavaScript object.
         * 
         * @param {string} name Object name.
         * @param {Object} obj Object to inspect.
         */
        inspect: function( name, obj ) {
            console.group( '[SCD Inspect] ' + name );
            console.log( obj );
            console.groupEnd();
            
            if ( window.SCD_Debug_Console && window.SCD_Debug_Console.enabled ) {
                var output = $( '#scd-debug-inspector-output' );
                if ( output.length ) {
                    var data = 'JavaScript Inspection: ' + name + '\n' + JSON.stringify( obj, null, 2 ) + '\n\n';
                    output.append( data );
                    SCD_Debug_Console_Manager.scrollToBottom( '#scd-debug-inspector-output' );
                }
            }
        }
    };
    
    // Initialize when DOM is ready
    $( document ).ready( function() {
        SCD_Debug_Console_Manager.init();
    } );
    
} )( jQuery );