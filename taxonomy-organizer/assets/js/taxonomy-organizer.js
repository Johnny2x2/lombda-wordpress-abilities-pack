/**
 * Taxonomy Organizer JavaScript
 */
(function($) {
    'use strict';

    var TaxonomyOrganizer = {
        currentTaxonomy: '',
        allTerms: [],
        hierarchicalTerms: [],
        
        init: function() {
            this.bindEvents();
            this.loadTerms();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Taxonomy selector change
            $('#taxorg-taxonomy-select').on('change', function() {
                self.currentTaxonomy = $(this).val();
                $('#taxorg-tree').data('taxonomy', self.currentTaxonomy);
                self.loadTerms();
            });
            
            // Expand/Collapse all
            $('#taxorg-expand-all').on('click', function() {
                $('.taxorg-term-item.collapsed').removeClass('collapsed');
            });
            
            $('#taxorg-collapse-all').on('click', function() {
                $('.taxorg-term-item.has-children').addClass('collapsed');
            });
            
            // Toggle individual items
            $(document).on('click', '.taxorg-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.taxorg-term-item').toggleClass('collapsed');
            });
            
            // Quick parent change button
            $(document).on('click', '.taxorg-quick-parent', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $(this).closest('.taxorg-term-item');
                var termId = $item.data('term-id');
                var termName = $item.find('> .taxorg-term-row .taxorg-term-name').text();
                self.openParentModal(termId, termName);
            });
            
            // Modal close
            $(document).on('click', '.taxorg-modal-close, .taxorg-modal-cancel', function() {
                self.closeModals();
            });
            
            // Close modal on outside click
            $(document).on('click', '.taxorg-modal', function(e) {
                if ($(e.target).hasClass('taxorg-modal')) {
                    self.closeModals();
                }
            });
            
            // Save parent change from modal
            $(document).on('click', '.taxorg-modal-save', function() {
                var termId = $('#taxorg-modal-term-id').val();
                var newParent = $('#taxorg-new-parent').val();
                var taxonomy = self.currentTaxonomy || $('#taxorg-tree').data('taxonomy');
                self.updateTermParent(termId, newParent, taxonomy);
            });
            
            // Inline parent change from term list
            $(document).on('click', '.taxorg-inline-change-parent', function(e) {
                e.preventDefault();
                var termId = $(this).data('term-id');
                var taxonomy = $(this).data('taxonomy');
                var termName = $(this).data('term-name');
                self.openInlineParentModal(termId, termName, taxonomy);
            });
            
            // Save inline parent change
            $(document).on('click', '.taxorg-inline-modal-save', function() {
                var termId = $('#taxorg-inline-modal-term-id').val();
                var newParent = $('#taxorg-inline-new-parent').val();
                var taxonomy = $('#taxorg-inline-modal-taxonomy').val();
                self.updateTermParent(termId, newParent, taxonomy, true);
            });
            
            // ESC key to close modals
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    self.closeModals();
                }
            });
            
            // Add Term button
            $('#taxorg-add-term').on('click', function() {
                self.openAddTermModal();
            });
            
            // Save new term
            $(document).on('click', '.taxorg-add-modal-save', function() {
                self.saveNewTerm();
            });
            
            // Enter key in name field to save
            $(document).on('keypress', '#taxorg-new-term-name', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.saveNewTerm();
                }
            });
            
            // Close searchable dropdowns when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.taxorg-searchable-select').length) {
                    // Close all dropdowns and reset state
                    $('.taxorg-searchable-select').removeClass('open');
                    $('.taxorg-searchable-select').each(function() {
                        $(this).find('.taxorg-search-input').val('').hide();
                        $(this).find('.taxorg-selected-text').show();
                        $(this).find('.taxorg-option').show().removeClass('highlighted');
                    });
                }
                // Close quick add form when clicking outside
                if (!$(e.target).closest('.taxorg-quick-add-form').length && 
                    !$(e.target).closest('.taxorg-quick-add').length) {
                    self.closeQuickAddForm();
                }
            });
            
            // Quick Add button on term rows
            $(document).on('click', '.taxorg-quick-add', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $item = $(this).closest('.taxorg-term-item');
                var termId = $item.data('term-id');
                var parentId = $item.data('parent-id') || 0;
                var hasChildren = $(this).data('has-children') == 1 || $item.hasClass('has-children');
                
                // If term has children, add as child of this term
                // If term has no children, add as sibling (same parent)
                var newParentId = hasChildren ? termId : parentId;
                
                self.openQuickAddForm($item, newParentId, termId, hasChildren);
            });
            
            // Quick Add form save
            $(document).on('click', '.taxorg-quick-add-save', function(e) {
                e.preventDefault();
                self.saveQuickAddTerm();
            });
            
            // Quick Add form cancel
            $(document).on('click', '.taxorg-quick-add-cancel', function(e) {
                e.preventDefault();
                self.closeQuickAddForm();
            });
            
            // Quick Add form enter key
            $(document).on('keypress', '#taxorg-quick-add-name', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.saveQuickAddTerm();
                }
            });
            
            // Quick Add form escape key
            $(document).on('keyup', '#taxorg-quick-add-name', function(e) {
                if (e.key === 'Escape') {
                    self.closeQuickAddForm();
                }
            });
        },
        
        openQuickAddForm: function($contextItem, parentId, contextTermId, addAsChild) {
            var self = this;
            var $form = $('#taxorg-quick-add-form');
            
            // Close any existing form first
            this.closeQuickAddForm();
            
            // Set the parent and context
            $('#taxorg-quick-add-parent').val(parentId);
            $('#taxorg-quick-add-context-term').val(contextTermId);
            $form.data('add-as-child', addAsChild);
            $form.data('context-item', $contextItem);
            
            // Position the form
            if (addAsChild) {
                // Add as child - show form inside the parent's child list
                var $childList = $contextItem.find('> .taxorg-term-list');
                if (!$childList.length) {
                    // Create temporary child list
                    $childList = $('<ul class="taxorg-term-list taxorg-temp-list"></ul>');
                    $contextItem.append($childList);
                }
                $childList.prepend($form);
            } else {
                // Add as sibling - show form after the context item
                $contextItem.after($form);
            }
            
            // Show and focus
            $form.show().addClass('taxorg-quick-add-active');
            $('#taxorg-quick-add-name').val('').focus();
        },
        
        closeQuickAddForm: function() {
            var $form = $('#taxorg-quick-add-form');
            $form.hide().removeClass('taxorg-quick-add-active');
            $('#taxorg-quick-add-name').val('');
            
            // Remove any temporary empty child lists
            $('.taxorg-temp-list:empty').remove();
            
            // Move form back to body to avoid issues
            $('body').append($form);
        },
        
        saveQuickAddTerm: function() {
            var self = this;
            var name = $('#taxorg-quick-add-name').val().trim();
            var parentId = $('#taxorg-quick-add-parent').val();
            var contextTermId = $('#taxorg-quick-add-context-term').val();
            var $form = $('#taxorg-quick-add-form');
            var addAsChild = $form.data('add-as-child');
            var $contextItem = $form.data('context-item');
            var taxonomy = this.currentTaxonomy || $('#taxorg-tree').data('taxonomy');
            
            if (!name) {
                $('#taxorg-quick-add-name').focus();
                return;
            }
            
            // Disable form while saving
            $form.addClass('taxorg-saving');
            $('.taxorg-quick-add-save').prop('disabled', true);
            
            $.ajax({
                url: taxorgData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'taxorg_add_term',
                    name: name,
                    parent: parentId,
                    taxonomy: taxonomy,
                    nonce: taxorgData.nonce
                },
                success: function(response) {
                    $form.removeClass('taxorg-saving');
                    $('.taxorg-quick-add-save').prop('disabled', false);
                    
                    if (response.success) {
                        var newTerm = response.data.term;
                        
                        // Create new term element
                        var $newItem = self.createTermElement(newTerm);
                        
                        // Insert in the right place
                        if (addAsChild) {
                            // Add as child of context item
                            var $childList = $contextItem.find('> .taxorg-term-list');
                            if (!$childList.length) {
                                $childList = $('<ul class="taxorg-term-list"></ul>');
                                $contextItem.append($childList);
                            }
                            $childList.removeClass('taxorg-temp-list');
                            $form.after($newItem);
                            
                            // Make sure parent shows as having children
                            $contextItem.addClass('has-children');
                            var $row = $contextItem.find('> .taxorg-term-row');
                            if (!$row.find('.taxorg-toggle').length) {
                                $row.find('.taxorg-toggle-placeholder').replaceWith(
                                    '<span class="taxorg-toggle dashicons dashicons-arrow-down-alt2"></span>'
                                );
                            }
                            // Update the quick-add button data
                            $row.find('.taxorg-quick-add').data('has-children', 1);
                        } else {
                            // Add as sibling after form
                            $form.after($newItem);
                        }
                        
                        // Animate the new item
                        $newItem.addClass('taxorg-moving');
                        setTimeout(function() {
                            $newItem.removeClass('taxorg-moving');
                        }, 300);
                        
                        // Initialize drag-drop for new item
                        self.reinitDragDropForItem($newItem);
                        
                        // Update internal data
                        self.allTerms.push(newTerm);
                        self.hierarchicalTerms = self.buildHierarchicalFromFlat(self.allTerms);
                        
                        // Clear and prepare for another entry
                        $('#taxorg-quick-add-name').val('').focus();
                        
                        self.showNotice('Term "' + name + '" added!', 'success');
                    } else {
                        self.showNotice(response.data || 'Error adding term.', 'error');
                    }
                },
                error: function() {
                    $form.removeClass('taxorg-saving');
                    $('.taxorg-quick-add-save').prop('disabled', false);
                    self.showNotice('Error adding term. Please try again.', 'error');
                }
            });
        },
        
        createTermElement: function(term) {
            var $item = $('<li class="taxorg-term-item" data-term-id="' + term.term_id + '" data-parent-id="' + term.parent + '"></li>');
            
            var $row = $('<div class="taxorg-term-row"></div>');
            $row.append('<span class="taxorg-toggle-placeholder"></span>');
            $row.append('<span class="taxorg-drag-handle dashicons dashicons-move"></span>');
            $row.append('<span class="taxorg-term-name">' + this.escapeHtml(term.name) + '</span>');
            $row.append('<span class="taxorg-term-count">(0)</span>');
            
            var $actions = $('<span class="taxorg-term-actions"></span>');
            $actions.append('<button type="button" class="taxorg-quick-add button-link" title="Add Term" data-has-children="0"><span class="dashicons dashicons-plus-alt"></span></button>');
            $actions.append('<button type="button" class="taxorg-quick-parent button-link" title="Change Parent"><span class="dashicons dashicons-networking"></span></button>');
            $actions.append('<a href="' + this.getEditTermUrl(term.term_id) + '" class="taxorg-edit-link" title="Edit Term"><span class="dashicons dashicons-edit"></span></a>');
            $row.append($actions);
            
            $item.append($row);
            
            return $item;
        },
        
        getEditTermUrl: function(termId) {
            // Build edit term URL
            var taxonomy = this.currentTaxonomy || 'category';
            return ajaxurl.replace('/admin-ajax.php', '/term.php?taxonomy=' + taxonomy + '&tag_ID=' + termId);
        },
        
        loadTerms: function() {
            var self = this;
            var $tree = $('#taxorg-tree');
            var taxonomy = $tree.data('taxonomy') || 'category';
            
            this.currentTaxonomy = taxonomy;
            
            $tree.html('<div class="taxorg-loading">' + taxorgData.strings.loading + '</div>');
            
            $.ajax({
                url: taxorgData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'taxorg_get_terms',
                    taxonomy: taxonomy,
                    nonce: taxorgData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $tree.html(response.data.html);
                        self.allTerms = response.data.terms;
                        self.hierarchicalTerms = response.data.hierarchicalTerms || [];
                        self.initDragDrop();
                        
                        if (!response.data.html || response.data.html.trim() === '<ul class="taxorg-term-list" data-level="0"></ul>') {
                            $tree.html('<div class="taxorg-empty"><span class="dashicons dashicons-category"></span><p>No terms found in this taxonomy.</p></div>');
                        }
                    } else {
                        $tree.html('<div class="taxorg-empty"><p>Error loading terms: ' + response.data + '</p></div>');
                    }
                },
                error: function() {
                    $tree.html('<div class="taxorg-empty"><p>Error loading terms. Please try again.</p></div>');
                }
            });
        },
        
        initDragDrop: function() {
            var self = this;
            
            // Make terms draggable
            $('.taxorg-term-item').draggable({
                handle: '.taxorg-drag-handle',
                helper: function() {
                    var $clone = $(this).clone();
                    $clone.find('.taxorg-term-list').remove(); // Remove children from helper
                    $clone.addClass('taxorg-drag-helper');
                    return $clone;
                },
                opacity: 1,
                cursor: 'grabbing',
                cursorAt: { left: 30, top: 20 },
                zIndex: 10000,
                revert: 'invalid',
                revertDuration: 200,
                scroll: true,
                scrollSensitivity: 50,
                scrollSpeed: 20,
                start: function(event, ui) {
                    $(this).addClass('taxorg-dragging');
                    ui.helper.css({
                        'width': $(this).find('> .taxorg-term-row').outerWidth() + 'px',
                        'box-shadow': '0 8px 25px rgba(0,0,0,0.25)',
                        'transform': 'rotate(2deg)'
                    });
                    // Store original position info
                    $(this).data('original-parent', $(this).parent().closest('.taxorg-term-item').data('term-id') || 0);
                },
                stop: function() {
                    $(this).removeClass('taxorg-dragging');
                }
            });
            
            // Make terms droppable (for nesting)
            $('.taxorg-term-item').droppable({
                accept: '.taxorg-term-item',
                hoverClass: 'drop-target',
                tolerance: 'pointer',
                greedy: true,
                drop: function(event, ui) {
                    var $dragged = ui.draggable;
                    var $target = $(this);
                    var draggedTermId = $dragged.data('term-id');
                    var targetTermId = $target.data('term-id');
                    
                    // Don't drop on itself
                    if (draggedTermId === targetTermId) {
                        return false;
                    }
                    
                    // Don't drop on own children (prevent circular reference)
                    if ($target.closest('[data-term-id="' + draggedTermId + '"]').length) {
                        self.showNotice('Cannot move a term into its own child!', 'error');
                        return false;
                    }
                    
                    // Move the element in DOM immediately for instant feedback
                    self.moveTermInDOM($dragged, $target, targetTermId);
                    
                    // Save to database in background
                    self.saveTermParent(draggedTermId, targetTermId, self.currentTaxonomy);
                }
            });
            
            // Root drop zone
            $('#taxorg-root-drop').droppable({
                accept: '.taxorg-term-item',
                hoverClass: 'drag-over',
                tolerance: 'pointer',
                drop: function(event, ui) {
                    var $dragged = ui.draggable;
                    var draggedTermId = $dragged.data('term-id');
                    
                    // Move to root level in DOM
                    self.moveTermToRoot($dragged);
                    
                    // Save to database in background
                    self.saveTermParent(draggedTermId, 0, self.currentTaxonomy);
                }
            });
        },
        
        moveTermInDOM: function($item, $newParent, newParentId) {
            var self = this;
            
            // Add animation class
            $item.addClass('taxorg-moving');
            
            // Check if new parent has a child list, create if not
            var $childList = $newParent.find('> .taxorg-term-list');
            if (!$childList.length) {
                $childList = $('<ul class="taxorg-term-list"></ul>');
                $newParent.append($childList);
                $newParent.addClass('has-children');
                
                // Add toggle arrow if not present
                var $row = $newParent.find('> .taxorg-term-row');
                if (!$row.find('.taxorg-toggle').length) {
                    $row.find('.taxorg-toggle-placeholder').replaceWith(
                        '<span class="taxorg-toggle dashicons dashicons-arrow-down-alt2"></span>'
                    );
                }
            }
            
            // Detach and move the item
            $item.detach();
            $childList.append($item);
            
            // Update data attribute
            $item.data('parent-id', newParentId);
            $item.attr('data-parent-id', newParentId);
            
            // Cleanup old parent if it has no more children
            this.cleanupEmptyParents();
            
            // Re-init drag drop for moved item
            setTimeout(function() {
                $item.removeClass('taxorg-moving');
                self.reinitDragDropForItem($item);
            }, 300);
        },
        
        moveTermToRoot: function($item) {
            var self = this;
            
            // Add animation class
            $item.addClass('taxorg-moving');
            
            // Get the root list
            var $rootList = $('#taxorg-tree > .taxorg-term-list');
            if (!$rootList.length) {
                $rootList = $('<ul class="taxorg-term-list" data-level="0"></ul>');
                $('#taxorg-tree').html($rootList);
            }
            
            // Detach and move to root
            $item.detach();
            $rootList.append($item);
            
            // Update data attribute
            $item.data('parent-id', 0);
            $item.attr('data-parent-id', 0);
            
            // Cleanup old parent if it has no more children
            this.cleanupEmptyParents();
            
            // Re-init drag drop for moved item
            setTimeout(function() {
                $item.removeClass('taxorg-moving');
                self.reinitDragDropForItem($item);
            }, 300);
        },
        
        cleanupEmptyParents: function() {
            // Find parents with empty child lists and clean them up
            $('.taxorg-term-item').each(function() {
                var $childList = $(this).find('> .taxorg-term-list');
                if ($childList.length && $childList.children().length === 0) {
                    $childList.remove();
                    $(this).removeClass('has-children');
                    
                    // Replace toggle with placeholder
                    var $toggle = $(this).find('> .taxorg-term-row .taxorg-toggle');
                    if ($toggle.length) {
                        $toggle.replaceWith('<span class="taxorg-toggle-placeholder"></span>');
                    }
                }
            });
        },
        
        reinitDragDropForItem: function($item) {
            var self = this;
            
            // Destroy existing draggable/droppable
            if ($item.data('ui-draggable')) {
                $item.draggable('destroy');
            }
            if ($item.data('ui-droppable')) {
                $item.droppable('destroy');
            }
            
            // Reinitialize draggable
            $item.draggable({
                handle: '.taxorg-drag-handle',
                helper: function() {
                    var $clone = $(this).clone();
                    $clone.find('.taxorg-term-list').remove();
                    $clone.addClass('taxorg-drag-helper');
                    return $clone;
                },
                opacity: 1,
                cursor: 'grabbing',
                cursorAt: { left: 30, top: 20 },
                zIndex: 10000,
                revert: 'invalid',
                revertDuration: 200,
                scroll: true,
                scrollSensitivity: 50,
                scrollSpeed: 20,
                start: function(event, ui) {
                    $(this).addClass('taxorg-dragging');
                    ui.helper.css({
                        'width': $(this).find('> .taxorg-term-row').outerWidth() + 'px',
                        'box-shadow': '0 8px 25px rgba(0,0,0,0.25)',
                        'transform': 'rotate(2deg)'
                    });
                },
                stop: function() {
                    $(this).removeClass('taxorg-dragging');
                }
            });
            
            // Reinitialize droppable
            $item.droppable({
                accept: '.taxorg-term-item',
                hoverClass: 'drop-target',
                tolerance: 'pointer',
                greedy: true,
                drop: function(event, ui) {
                    var $dragged = ui.draggable;
                    var $target = $(this);
                    var draggedTermId = $dragged.data('term-id');
                    var targetTermId = $target.data('term-id');
                    
                    if (draggedTermId === targetTermId) {
                        return false;
                    }
                    
                    if ($target.closest('[data-term-id="' + draggedTermId + '"]').length) {
                        self.showNotice('Cannot move a term into its own child!', 'error');
                        return false;
                    }
                    
                    self.moveTermInDOM($dragged, $target, targetTermId);
                    self.saveTermParent(draggedTermId, targetTermId, self.currentTaxonomy);
                }
            });
            
            // Also reinit for any children
            $item.find('.taxorg-term-item').each(function() {
                self.reinitDragDropForItem($(this));
            });
        },
        
        saveTermParent: function(termId, newParent, taxonomy) {
            var self = this;
            
            // Show saving indicator
            var $item = $('.taxorg-term-item[data-term-id="' + termId + '"]');
            $item.find('> .taxorg-term-row').addClass('taxorg-saving');
            
            $.ajax({
                url: taxorgData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'taxorg_update_term_parent',
                    term_id: termId,
                    new_parent: newParent,
                    taxonomy: taxonomy,
                    nonce: taxorgData.nonce
                },
                success: function(response) {
                    $item.find('> .taxorg-term-row').removeClass('taxorg-saving');
                    
                    if (response.success) {
                        // Brief success flash
                        $item.find('> .taxorg-term-row').addClass('taxorg-saved');
                        setTimeout(function() {
                            $item.find('> .taxorg-term-row').removeClass('taxorg-saved');
                        }, 1000);
                        
                        // Update internal data
                        self.updateTermData(termId, newParent);
                    } else {
                        self.showNotice(response.data || taxorgData.strings.error, 'error');
                        // Reload to restore correct state
                        self.loadTerms();
                    }
                },
                error: function() {
                    $item.find('> .taxorg-term-row').removeClass('taxorg-saving');
                    self.showNotice(taxorgData.strings.error, 'error');
                    // Reload to restore correct state
                    self.loadTerms();
                }
            });
        },
        
        updateTermData: function(termId, newParent) {
            // Update allTerms array
            for (var i = 0; i < this.allTerms.length; i++) {
                if (this.allTerms[i].term_id === termId) {
                    this.allTerms[i].parent = newParent;
                    break;
                }
            }
            
            // Rebuild hierarchical terms
            this.hierarchicalTerms = this.buildHierarchicalFromFlat(this.allTerms);
        },
        
        buildHierarchicalFromFlat: function(terms, parentId, depth) {
            parentId = parentId || 0;
            depth = depth || 0;
            var result = [];
            var self = this;
            
            terms.forEach(function(term) {
                if (term.parent == parentId) {
                    result.push({
                        term_id: term.term_id,
                        name: term.name,
                        parent: term.parent,
                        depth: depth
                    });
                    var children = self.buildHierarchicalFromFlat(terms, term.term_id, depth + 1);
                    result = result.concat(children);
                }
            });
            
            return result;
        },
        
        openParentModal: function(termId, termName) {
            var self = this;
            var $modal = $('#taxorg-parent-modal');
            var $container = $modal.find('.taxorg-modal-body');
            
            // Build options excluding the current term and its children
            var excludeIds = this.getTermAndChildren(termId);
            
            // Set current parent
            var currentItem = $('.taxorg-term-item[data-term-id="' + termId + '"]');
            var currentParent = currentItem.data('parent-id') || 0;
            
            // Create searchable dropdown
            this.createSearchableDropdown($container, 'taxorg-new-parent', excludeIds, currentParent);
            
            $('#taxorg-modal-term-id').val(termId);
            $modal.find('.taxorg-modal-term-name').text(termName);
            
            $modal.fadeIn(200);
            
            // Focus on search
            setTimeout(function() {
                $modal.find('.taxorg-search-input').focus();
            }, 250);
        },
        
        openAddTermModal: function() {
            var self = this;
            var $modal = $('#taxorg-add-modal');
            var $container = $modal.find('label[for="taxorg-new-term-parent"]').closest('.taxorg-form-field');
            
            // Create searchable dropdown
            this.createSearchableDropdown($container, 'taxorg-new-term-parent', [], 0);
            
            // Clear form fields
            $('#taxorg-new-term-name').val('');
            $('#taxorg-new-term-slug').val('');
            $('#taxorg-new-term-description').val('');
            
            $modal.fadeIn(200);
            
            // Focus on name field
            setTimeout(function() {
                $('#taxorg-new-term-name').focus();
            }, 250);
        },
        
        createSearchableDropdown: function($container, inputId, excludeIds, selectedValue) {
            var self = this;
            excludeIds = excludeIds || [];
            selectedValue = selectedValue || 0;
            
            // Get selected text
            var selectedText = taxorgData.strings.noParent;
            if (selectedValue != 0) {
                for (var i = 0; i < this.hierarchicalTerms.length; i++) {
                    if (this.hierarchicalTerms[i].term_id == selectedValue) {
                        selectedText = this.hierarchicalTerms[i].name;
                        break;
                    }
                }
            }
            
            // Build the dropdown HTML
            var html = '<div class="taxorg-searchable-select" data-input-id="' + inputId + '">';
            html += '<input type="hidden" id="' + inputId + '" value="' + selectedValue + '">';
            html += '<div class="taxorg-select-display">';
            html += '<span class="taxorg-selected-text">' + this.escapeHtml(selectedText) + '</span>';
            html += '<input type="text" class="taxorg-search-input" placeholder="Type to filter...">';
            html += '<span class="taxorg-select-arrow dashicons dashicons-arrow-down-alt2"></span>';
            html += '</div>';
            html += '<div class="taxorg-select-dropdown">';
            html += '<div class="taxorg-select-options">';
            html += this.buildOptionsHtml(excludeIds, selectedValue);
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Replace existing select or searchable dropdown
            var $existing = $container.find('.taxorg-searchable-select, select#' + inputId);
            if ($existing.length) {
                $existing.replaceWith(html);
            } else {
                $container.find('label').after(html);
            }
            
            // Initialize the dropdown
            this.initSearchableDropdown($container.find('.taxorg-searchable-select'));
        },
        
        buildOptionsHtml: function(excludeIds, selectedValue) {
            var self = this;
            excludeIds = excludeIds || [];
            var html = '';
            
            // Add "No Parent" option
            var noParentClass = selectedValue == 0 ? 'taxorg-option selected' : 'taxorg-option';
            html += '<div class="' + noParentClass + '" data-value="0">';
            html += '<span class="taxorg-option-text">' + taxorgData.strings.noParent + '</span>';
            html += '</div>';
            
            // Add hierarchical terms
            this.hierarchicalTerms.forEach(function(term) {
                if (excludeIds.indexOf(term.term_id) === -1) {
                    var indent = '—'.repeat(term.depth);
                    var paddingLeft = term.depth * 20;
                    var selectedClass = term.term_id == selectedValue ? ' selected' : '';
                    
                    html += '<div class="taxorg-option' + selectedClass + '" data-value="' + term.term_id + '" style="padding-left: ' + (12 + paddingLeft) + 'px;">';
                    if (indent) {
                        html += '<span class="taxorg-option-indent">' + indent + '</span> ';
                    }
                    html += '<span class="taxorg-option-text">' + self.escapeHtml(term.name) + '</span>';
                    html += '</div>';
                }
            });
            
            return html;
        },
        
        initSearchableDropdown: function($dropdown) {
            var self = this;
            var $input = $dropdown.find('.taxorg-search-input');
            var $selectedText = $dropdown.find('.taxorg-selected-text');
            var $optionsContainer = $dropdown.find('.taxorg-select-dropdown');
            var $hiddenInput = $dropdown.find('input[type="hidden"]');
            
            // Toggle dropdown on click
            $dropdown.find('.taxorg-select-display').on('click', function(e) {
                e.stopPropagation();
                var isOpen = $dropdown.hasClass('open');
                
                // Close all other dropdowns
                $('.taxorg-searchable-select').removeClass('open');
                $('.taxorg-searchable-select').find('.taxorg-search-input').val('').hide();
                $('.taxorg-searchable-select').find('.taxorg-selected-text').show();
                $('.taxorg-searchable-select').find('.taxorg-option').show().removeClass('highlighted');
                
                if (!isOpen) {
                    $dropdown.addClass('open');
                    // Show search input, hide selected text
                    $selectedText.hide();
                    $input.show().val('').focus();
                }
            });
            
            // Filter options on typing
            $input.on('input', function() {
                var query = $(this).val().toLowerCase().trim();
                var hasVisibleOptions = false;
                
                $dropdown.find('.taxorg-option').each(function() {
                    var text = $(this).find('.taxorg-option-text').text().toLowerCase();
                    if (query === '' || text.indexOf(query) > -1) {
                        $(this).show();
                        hasVisibleOptions = true;
                    } else {
                        $(this).hide();
                    }
                });
                
                // Always show "No Parent" option
                $dropdown.find('.taxorg-option[data-value="0"]').show();
                
                // Highlight first visible option
                $dropdown.find('.taxorg-option').removeClass('highlighted');
                $dropdown.find('.taxorg-option:visible').first().addClass('highlighted');
            });
            
            // Prevent typing anything that's not for filtering
            $input.on('blur', function() {
                // Small delay to allow click on option
                setTimeout(function() {
                    if (!$dropdown.hasClass('open')) {
                        $input.val('').hide();
                        $selectedText.show();
                        $dropdown.find('.taxorg-option').show().removeClass('highlighted');
                    }
                }, 150);
            });
            
            // Select option on click
            $dropdown.find('.taxorg-option').on('click', function(e) {
                e.stopPropagation();
                var value = $(this).data('value');
                var text = $(this).find('.taxorg-option-text').text();
                
                $hiddenInput.val(value);
                $dropdown.find('.taxorg-option').removeClass('selected');
                $(this).addClass('selected');
                
                // Update selected text display
                $selectedText.text(text).show();
                $input.val('').hide();
                $dropdown.removeClass('open');
                
                // Reset filter
                $dropdown.find('.taxorg-option').show().removeClass('highlighted');
            });
            
            // Keyboard navigation
            $input.on('keydown', function(e) {
                var $visible = $dropdown.find('.taxorg-option:visible');
                var $current = $dropdown.find('.taxorg-option.highlighted');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!$dropdown.hasClass('open')) {
                        $dropdown.find('.taxorg-select-display').click();
                        return;
                    }
                    if (!$current.length) {
                        $visible.first().addClass('highlighted');
                    } else {
                        var $next = $current.nextAll('.taxorg-option:visible').first();
                        if ($next.length) {
                            $current.removeClass('highlighted');
                            $next.addClass('highlighted');
                            self.scrollOptionIntoView($next, $optionsContainer);
                        }
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if ($current.length) {
                        var $prev = $current.prevAll('.taxorg-option:visible').first();
                        if ($prev.length) {
                            $current.removeClass('highlighted');
                            $prev.addClass('highlighted');
                            self.scrollOptionIntoView($prev, $optionsContainer);
                        }
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if ($current.length) {
                        $current.click();
                    } else {
                        // Select first visible option
                        $visible.first().click();
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    $dropdown.removeClass('open');
                    $input.val('').hide();
                    $selectedText.show();
                    $dropdown.find('.taxorg-option').show().removeClass('highlighted');
                }
            });
            
            // Hover highlighting
            $dropdown.find('.taxorg-option').on('mouseenter', function() {
                $dropdown.find('.taxorg-option').removeClass('highlighted');
                $(this).addClass('highlighted');
            });
        },
        
        scrollOptionIntoView: function($option, $container) {
            var optionTop = $option.position().top;
            var optionHeight = $option.outerHeight();
            var containerHeight = $container.height();
            var scrollTop = $container.scrollTop();
            
            if (optionTop < 0) {
                $container.scrollTop(scrollTop + optionTop);
            } else if (optionTop + optionHeight > containerHeight) {
                $container.scrollTop(scrollTop + optionTop + optionHeight - containerHeight);
            }
        },
        
        updateSelectedDisplay: function($dropdown, value) {
            var $input = $dropdown.find('.taxorg-search-input');
            var selectedText = '';
            
            if (value == 0) {
                selectedText = taxorgData.strings.noParent;
            } else {
                // Find term name
                for (var i = 0; i < this.hierarchicalTerms.length; i++) {
                    if (this.hierarchicalTerms[i].term_id == value) {
                        selectedText = this.hierarchicalTerms[i].name;
                        break;
                    }
                }
            }
            
            $input.attr('placeholder', selectedText || 'Search or select parent...');
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        saveNewTerm: function() {
            var self = this;
            var name = $('#taxorg-new-term-name').val().trim();
            var slug = $('#taxorg-new-term-slug').val().trim();
            var parent = $('#taxorg-new-term-parent').val();
            var description = $('#taxorg-new-term-description').val().trim();
            var taxonomy = this.currentTaxonomy || $('#taxorg-tree').data('taxonomy');
            
            if (!name) {
                self.showNotice('Please enter a term name.', 'error');
                $('#taxorg-new-term-name').focus();
                return;
            }
            
            // Disable save button
            $('.taxorg-add-modal-save').prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: taxorgData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'taxorg_add_term',
                    name: name,
                    slug: slug,
                    parent: parent,
                    description: description,
                    taxonomy: taxonomy,
                    nonce: taxorgData.nonce
                },
                success: function(response) {
                    $('.taxorg-add-modal-save').prop('disabled', false).text('Add Term');
                    
                    if (response.success) {
                        self.showNotice('Term added successfully!', 'success');
                        self.closeModals();
                        self.loadTerms();
                    } else {
                        self.showNotice(response.data || 'Error adding term.', 'error');
                    }
                },
                error: function() {
                    $('.taxorg-add-modal-save').prop('disabled', false).text('Add Term');
                    self.showNotice('Error adding term. Please try again.', 'error');
                }
            });
        },
        
        openInlineParentModal: function(termId, termName, taxonomy) {
            var $modal = $('#taxorg-inline-parent-modal');
            
            // Remove the current term from the select options
            var $select = $('#taxorg-inline-new-parent');
            $select.find('option[value="' + termId + '"]').prop('disabled', true);
            
            $('#taxorg-inline-modal-term-id').val(termId);
            $('#taxorg-inline-modal-taxonomy').val(taxonomy);
            $('.taxorg-modal-term-name').text(termName);
            
            $modal.fadeIn(200);
        },
        
        closeModals: function() {
            $('.taxorg-modal').fadeOut(200);
        },
        
        updateTermParent: function(termId, newParent, taxonomy, reload) {
            var self = this;
            
            // For modal-based changes, move in DOM first for instant feedback
            var $item = $('.taxorg-term-item[data-term-id="' + termId + '"]');
            
            if (!reload && $item.length) {
                if (newParent == 0) {
                    self.moveTermToRoot($item);
                } else {
                    var $newParent = $('.taxorg-term-item[data-term-id="' + newParent + '"]');
                    if ($newParent.length) {
                        self.moveTermInDOM($item, $newParent, newParent);
                    }
                }
                
                // Close modal immediately
                self.closeModals();
                
                // Save in background
                self.saveTermParent(termId, newParent, taxonomy);
                return;
            }
            
            // Fallback for inline modal (on edit-tags.php)
            $.ajax({
                url: taxorgData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'taxorg_update_term_parent',
                    term_id: termId,
                    new_parent: newParent,
                    taxonomy: taxonomy,
                    nonce: taxorgData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(taxorgData.strings.success, 'success');
                        self.closeModals();
                        
                        if (reload) {
                            // Reload the page to see changes in the term list
                            location.reload();
                        }
                    } else {
                        self.showNotice(response.data || taxorgData.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showNotice(taxorgData.strings.error, 'error');
                }
            });
        },
        
        getTermAndChildren: function(termId) {
            var ids = [termId];
            var self = this;
            
            this.allTerms.forEach(function(term) {
                if (term.parent === termId) {
                    ids = ids.concat(self.getTermAndChildren(term.term_id));
                }
            });
            
            return ids;
        },
        
        getTermDepth: function(termId) {
            var depth = 0;
            var term = this.findTermById(termId);
            
            while (term && term.parent > 0) {
                depth++;
                term = this.findTermById(term.parent);
            }
            
            return depth;
        },
        
        findTermById: function(termId) {
            for (var i = 0; i < this.allTerms.length; i++) {
                if (this.allTerms[i].term_id === termId) {
                    return this.allTerms[i];
                }
            }
            return null;
        },
        
        showNotice: function(message, type) {
            // Remove existing notices
            $('.taxorg-notice').remove();
            
            var $notice = $('<div class="taxorg-notice ' + type + '">' + message + '</div>');
            $('body').append($notice);
            
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Only init on our admin page
        if ($('#taxorg-tree').length) {
            TaxonomyOrganizer.init();
        }
        
        // Init inline parent change for edit-tags.php
        if ($('.taxorg-inline-change-parent').length || $('#taxorg-inline-parent-modal').length) {
            // Bind events for inline functionality
            $(document).on('click', '.taxorg-inline-change-parent', function(e) {
                e.preventDefault();
                var termId = $(this).data('term-id');
                var taxonomy = $(this).data('taxonomy');
                var termName = $(this).data('term-name');
                
                var $modal = $('#taxorg-inline-parent-modal');
                var $select = $('#taxorg-inline-new-parent');
                
                // Disable current term in select
                $select.find('option').prop('disabled', false);
                $select.find('option[value="' + termId + '"]').prop('disabled', true);
                
                $('#taxorg-inline-modal-term-id').val(termId);
                $('#taxorg-inline-modal-taxonomy').val(taxonomy);
                $modal.find('.taxorg-modal-term-name').text(termName);
                
                $modal.fadeIn(200);
            });
            
            // Close modal handlers
            $(document).on('click', '.taxorg-modal-close, .taxorg-modal-cancel', function() {
                $('.taxorg-modal').fadeOut(200);
            });
            
            $(document).on('click', '.taxorg-modal', function(e) {
                if ($(e.target).hasClass('taxorg-modal')) {
                    $('.taxorg-modal').fadeOut(200);
                }
            });
            
            // Save inline parent change
            $(document).on('click', '.taxorg-inline-modal-save', function() {
                var termId = $('#taxorg-inline-modal-term-id').val();
                var newParent = $('#taxorg-inline-new-parent').val();
                var taxonomy = $('#taxorg-inline-modal-taxonomy').val();
                
                $.ajax({
                    url: taxorgData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'taxorg_update_term_parent',
                        term_id: termId,
                        new_parent: newParent,
                        taxonomy: taxonomy,
                        nonce: taxorgData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload the page to see changes
                            location.reload();
                        } else {
                            alert(response.data || 'Error updating term parent.');
                        }
                    },
                    error: function() {
                        alert('Error updating term parent.');
                    }
                });
            });
        }
    });
    
})(jQuery);
