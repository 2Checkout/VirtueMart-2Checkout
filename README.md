# VirtueMart
2Checkout VirtueMart Connector

### _[Signup free with 2Checkout and start selling!](https://www.2checkout.com/signup)_

This repository includes plugins for each 2Checkout interface:
* **twocheckout** : 2PayJS/API
* **twocheckout_inline** : Inline Checkout
* **twocheckout_convert_plus** : Hosted Checkout

### Integrate VirtueMart with 2Checkout
----------------------------------------

### 2Checkout Payment Module Setup

#### 2Checkout Settings

1. Sign in to your 2Checkout account.
2. Navigate to **Dashboard** → **Integrations** → **Webhooks & API section**
3. There you can find the 'Merchant Code', 'Secret key', and the 'Buy link secret word'
4. Navigate to **Dashboard** → **Integrations** → **Ipn Settings**
5. Set the IPN URL which should be https://{your-site-name.com}/index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component
6. Enable 'Triggers' in the IPN section. It’s simpler to enable all the triggers. Those who are not required will simply not be used.

#### VirtueMart Settings

1. Login to your Virtuemar
2. In your Joomla admin, navigate to **Extensions** -> **Manage** -> **Install** and upload the plugin for the payment interface you wish to use.
3. Navigate to **Componets** -> **VirtueMart** -> **Payment Methods** and click **New**.
4. Check to enable.
5. Enter the name and description, set to published and save your changes. Click on Configuration.
6. Enter your **Seller ID** found in your 2Checkout panel Integrations section.
7. Enter your **Secret Key** found in your 2Checkout panel Integrations section.
8. Enter your **Secret Word** 2Checkout panel Integrations section _(Only used for Inline Checkout and Hosted Checkout modules)_
9. Set your currency and test/live mode settings.
10. Save your changes.
