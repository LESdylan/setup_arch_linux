/**
 * Sublime Tech theme scripts
 */
(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Initialize code highlighting with Prism.js if it exists
        if (typeof Prism !== 'undefined') {
            Prism.highlightAll();
        } else {
            // Load Prism.js dynamically if needed
            loadPrismJS();
        }

        // Add copy button to code blocks
        addCopyButtonToCodeBlocks();
        
        // Initialize mobile menu toggle
        mobileMenuToggle();
        
        // Add command line prompts to bash/shell code blocks
        addCommandLinePrompts();
    });

    /**
     * Load Prism.js dynamically
     */
    function loadPrismJS() {
        // Check if we have code blocks that need highlighting
        if ($('pre code').length === 0) {
            return;
        }

        // Create script element for Prism.js
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js';
        script.async = true;
        
        // Add onload handler
        script.onload = function() {
            // Load additional Prism components
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js');
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.js');
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/toolbar/prism-toolbar.min.js');
            loadScript('https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js');
        };
        
        // Append script to head
        document.head.appendChild(script);
    }

    /**
     * Load script helper function
     */
    function loadScript(src) {
        var script = document.createElement('script');
        script.src = src;
        script.async = true;
        document.head.appendChild(script);
    }

    /**
     * Add copy button to code blocks
     */
    function addCopyButtonToCodeBlocks() {
        $('pre:not(.toolbar-added)').each(function() {
            var $pre = $(this);
            $pre.addClass('toolbar-added');
            
            var $button = $('<button>', {
                'class': 'copy-button',
                'text': 'Copy',
                'click': function(e) {
                    e.preventDefault();
                    var code = $pre.find('code').text();
                    navigator.clipboard.writeText(code).then(function() {
                        $button.text('Copied!');
                        setTimeout(function() {
                            $button.text('Copy');
                        }, 2000);
                    });
                }
            });
            
            var $toolbar = $('<div>', {
                'class': 'code-toolbar'
            });
            
            $pre.wrap($toolbar);
            $pre.after($button);
        });
    }

    /**
     * Mobile menu toggle
     */
    function mobileMenuToggle() {
        var $menu = $('#primary-menu');
        var $toggle = $('<button>', {
            'class': 'menu-toggle',
            'aria-controls': 'primary-menu',
            'aria-expanded': 'false',
            'html': '<span class="screen-reader-text">Menu</span><span class="menu-icon"></span>'
        });
        
        $('#site-navigation').prepend($toggle);
        
        $toggle.on('click', function() {
            $(this).toggleClass('toggled');
            $menu.toggleClass('toggled');
            
            if ($(this).hasClass('toggled')) {
                $(this).attr('aria-expanded', 'true');
            } else {
                $(this).attr('aria-expanded', 'false');
            }
        });
    }

    /**
     * Add command line prompts to bash/shell code blocks
     */
    function addCommandLinePrompts() {
        $('pre code.language-bash, pre code.language-shell').each(function() {
            var $code = $(this);
            var html = $code.html();
            var lines = html.split('\n');
            var newHtml = '';
            
            // Create prompt wrapper
            newHtml += '<span class="command-line-prompt">';
            for (var i = 0; i < lines.length; i++) {
                // Check if line starts with sudo or is a root command
                if (lines[i].trim().startsWith('sudo ') || lines[i].trim().startsWith('# ')) {
                    newHtml += '<span data-user="root"></span>';
                } else {
                    newHtml += '<span></span>';
                }
            }
            newHtml += '</span>';
            
            // Append prompt to code block
            $code.html(html + newHtml);
        });
    }

})(jQuery);
