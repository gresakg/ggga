# GA for WP by GG

IMPORTANT this project has been discontinued. It's child is (Google Analytics Integration)[https://gitlab.com/gresakg/google-analytics-integration]

Dead simple wordpress plugin that inserts GA tracking code to your wordpress site.

## Instalation

1. Clone to your wp-content/plugins/ directory
2. Enable the plugin in your Wordpress administration
3. Go to Settings > General
4. Find the GA Tracking Id input field
5. Insert your tracking id
6. Save
7. Enjoy the stats

No code will be echoed unless the tracking code is present.

From version 2.0 you can optionally add the action hook that will echo your tracking code. By default this will be wp_head, but you could use get_footer or a specific theme hook if it exists (like after_body_tag).

From version 3.0 you can track Contact Form 7 submit events and outbound links.

From version 4.0 the plugin uses the new gtag.js tracking code by default for new users, but leaves the old code for compatibility reasons. Version 4.0 is also localization ready.

## License

GA for WP by GG simple plugin for inserting GA tracking code to your WP.

Copyright 2016 by Gregor Gresak razvoj@gresak.net

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; the version 2 of the License.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You can read the full text of this licence at the following link http://www.gnu.org/licenses/gpl-2.0.html
