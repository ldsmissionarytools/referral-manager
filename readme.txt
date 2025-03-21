# Referral Manager Plugin  

This is a WordPress and Elementor plugin designed to connect Elementor Forms to existing mission tools.  

## How to Translate the Plugin  

WordPress uses **Text Domains** to locate strings and load translations from `.mo` files.  

In this plugin, we use the text domain **`referral_manager`**, which will be referenced in the translation files.  

### Forcing Translation File Loading  

The following code ensures that the correct translation files are loaded:  

```php
function referral_manager_force_load_textdomain() {
    $locale = determine_locale();
    $mofile = plugin_dir_path(__FILE__) . "languages/referral-manager-$locale.mo";

    if (file_exists($mofile)) {
        load_textdomain('referral_manager', $mofile);
    }
}
add_action('plugins_loaded', 'referral_manager_force_load_textdomain');
```

### Directory Structure  

Make sure the translation files follow this structure:  

```plaintext
languages/ 
├── referral-manager-[locale].mo 
├── referral-manager-[locale].po
```

Replace `[locale]` with the appropriate language code.  
For example, for Brazilian Portuguese, use:  
referral-manager-pt_BR.po 
referral-manager-pt_BR.mo


### Tools for Editing `.po` and Compiling `.mo` Files  

Here are some recommended tools to manage your translation files:  

- [Eazy Po](http://www.eazypo.ca/)  
- [Poedit](https://poedit.net/)  
