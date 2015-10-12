# WP License Manager Client

Use this class together with the WP License Manager plugin from [Fourbean](http://fourbean.com) to
securely serve updates to your premium (or private) WordPress plugin or theme from your own web site.

## Contents

WP License Manager Client includes the following files:

* `README.md`: The file you are reading right now
* `LICENSE`: The GPL v3 license applied to this piece of code
* `class-wp-license-manager-client.php`: The license manager client class

## Usage

To use the *WP License Manager Client*, you will need two components:

1. A self-hosted WordPress site with the [WP License Manager plugin](TODO) installed.
1. A WordPress plugin or theme that you want to make use the license control.

Let's assume your WordPress site (license manager) is set up at `http://mylicenses.example.com`. In this case, 
you'll be able to access the licence manager API at `http://mylicenses.example.com/api/license-manager`.

To set up your WP License Manager Client using this license manager server:
 
### Step 1

Include this project in your WordPress theme or plugin. 

You have (at least) two options:

1. You can copy the file `class-wp-license-manager-client.php` into your project.
1. You can link this git project to your theme or plugin as a submodule.

### Step 2

Add the following line to a suitable place in your theme or plugin (for example `functions.php`)

    require_once( 'wp-license-manager-client/class-wp-license-manager-client.php' );
    
This assumes that the class is in a subdirectory called `wp-license-manager-client`. 
If you placed the class to your theme's codebase, edit the path accordingly.

### Step 3

Create an instance of the `Wp_License_Manager_Client` class. 

First, here's an example for a theme:

    $licence_manager = new Wp_License_Manager_Client(
        'product_id',
        'Theme Name',
        'textdomain',
        'http://mylicenses.example.com/api/license-manager',
        'theme'
    );

* `product_id` is the id you defined for the theme on your license manager site.
* `Theme Name` is the publicly visible name of your theme. It is used for creating a settings screen with the title
 "[Theme Name] License"
* `textdomain` is your theme's (or plugin's) text domain. Using the same localization file for your plugin and the license
management gives you more control over the texts.
* `http://mylicenses.example.com/api/license-manager` is the URL to your license manager site. (See above)
* `theme` specifies that you are using `Wp_License_Manager_Client` in a theme. It's also the default value for this 
parameter, so you can leave it out and everything will work just fine.

Then, a plugin:

    $licence_manager = new Wp_License_Manager_Client(
        'product_id',
        'Plugin Name',
        'textdomain',
        'http://mylicenses.example.com/api/license-manager',
        'plugin',
        __FILE__
    );

As you can see, this is mostly the same as above, except for the last two parameters:

* `plugin` defines that you are using the class in a plugin.
* `__FILE__` is the path to the plugin's main file. If you are instantiating the class outside the main file, make sure to 
  get this right.
  
And that's it. 

The code hooks into your theme or plugin and you're all set.

## Customizing the settings page

Depending on your theme or plugin, you may want to customize how the settings page is displayed. 

Currently, the best way to do this while making sure you can always update the license manager client 
to its newest version is to subclass `Wp_License_Manager_Client` and override its settings creation functions:

* `public function add_license_settings_fields()`: This function creates the settings items.
* `public function add_license_settings_page()`: This function creates the settings page.

## Questions and Suggestions

If you have any questions or suggestions for what you'd like to see in this class or the *WP License Manager* 
plugin, [send me email](mailto:jarkko@jarkkolaine.com).