=== Taxonomy Organizer ===
Contributors: santron
Tags: taxonomy, categories, terms, drag-and-drop, organize, hierarchy
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily organize taxonomy terms with an intuitive drag-and-drop interface and quick parent change options.

== Description ==

Taxonomy Organizer makes it easy to reorganize your WordPress taxonomy terms (categories, tags, and custom taxonomies) using a visual drag-and-drop interface.

**Features:**

* **Drag-and-Drop Interface** - Simply drag a term onto another to make it a child, or drop it in the "no parent" zone to make it a top-level term.
* **Quick Parent Change** - Change a term's parent directly from the term list using the quick action link.
* **Works with All Hierarchical Taxonomies** - Categories, WooCommerce product categories, and any custom hierarchical taxonomy.
* **Visual Tree View** - See your entire taxonomy hierarchy at a glance with expandable/collapsible branches.
* **No Page Reloads** - All changes are made via AJAX for a smooth, fast experience.

== Installation ==

1. Upload the `taxonomy-organizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools > Taxonomy Organizer to start organizing your terms

== Usage ==

**Using the Drag-and-Drop Interface:**

1. Navigate to Tools > Taxonomy Organizer
2. Select the taxonomy you want to organize from the dropdown
3. Drag any term by its move handle (⠿)
4. Drop it onto another term to make it a child
5. Or drop it in the "No Parent" zone on the left to make it a top-level term

**Using Quick Parent Change:**

1. Go to any taxonomy term list (e.g., Posts > Categories)
2. Hover over a term to see the row actions
3. Click "Change Parent"
4. Select the new parent from the dropdown
5. Click "Save Changes"

== Frequently Asked Questions ==

= Does this work with custom taxonomies? =

Yes! Taxonomy Organizer works with any hierarchical taxonomy registered in WordPress.

= Will this affect my posts? =

No, changing the parent of a term does not affect which posts are assigned to that term. It only changes the hierarchy structure.

= Can I undo a change? =

You can always drag a term back to its original position or use the parent change feature to restore the original parent.

== Screenshots ==

1. The main drag-and-drop interface
2. Quick parent change from the term list
3. The parent selection modal

== Changelog ==

= 1.0.0 =
* Initial release
* Drag-and-drop term organization
* Quick parent change from term list
* Support for all hierarchical taxonomies
* Expand/collapse all functionality
* AJAX-powered for smooth experience

== Upgrade Notice ==

= 1.0.0 =
Initial release of Taxonomy Organizer.
