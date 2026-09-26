<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Mallow API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
                    body .content .php-example code { display: none; }
                    body .content .python-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://mallow.test";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.11.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.11.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;,&quot;php&quot;,&quot;python&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                            <button type="button" class="lang-button" data-language-name="php">php</button>
                                            <button type="button" class="lang-button" data-language-name="python">python</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-endpoints" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endpoints">
                    <a href="#endpoints">Endpoints</a>
                </li>
                                    <ul id="tocify-subheader-endpoints" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endpoints-GETapi-plans">
                                <a href="#endpoints-GETapi-plans">List the merchant's plans (active only by default).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-plans">
                                <a href="#endpoints-POSTapi-plans">Create a plan for the merchant.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-customers">
                                <a href="#endpoints-GETapi-customers">List the merchant's customers.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-subscriptions">
                                <a href="#endpoints-POSTapi-subscriptions">Create a subscription for a merchant's customer.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PATCHapi-subscriptions--subscription_id--plan">
                                <a href="#endpoints-PATCHapi-subscriptions--subscription_id--plan">Change the subscription's plan (mid-cycle safe).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-subscriptions--subscription_id--invoice">
                                <a href="#endpoints-POSTapi-subscriptions--subscription_id--invoice">Generate the invoice for a billing period (current cycle by default).</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-merchants--id--dashboard">
                                <a href="#endpoints-GETapi-merchants--id--dashboard">Merchant dashboard metrics (Plan §5.2).</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-usage" class="tocify-header">
                <li class="tocify-item level-1" data-unique="usage">
                    <a href="#usage">Usage</a>
                </li>
                                    <ul id="tocify-subheader-usage" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="usage-POSTapi-usage">
                                <a href="#usage-POSTapi-usage">Record a usage event for the authenticated merchant's customer (idempotent).</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: September 26, 2026</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>Multi-tenant subscription billing &amp; usage-metering API. Every request is scoped to a merchant (tenant) resolved from its API key.</p>
<aside>
    <strong>Base URL</strong>: <code>http://mallow.test</code>
</aside>
<pre><code>## Tenancy (merchant scoping)

Every request is scoped to a **merchant (tenant)** — there is no global data. The tenant is resolved from the
`X-Api-Key` header (hashed lookup → merchant team), so all endpoints automatically operate on that merchant's
plans, customers, subscriptions, and usage. Cross-tenant access returns `404`.

Mint a key for your tenant:

```bash
php artisan billing:api-key --merchant=1 --name="docs key"
```

The web dashboard is tenant-chained by team slug (`/{team-slug}/dashboard`, e.g. `/acme-metering/dashboard`)
and uses the logged-in user's current team instead of an API key.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).&lt;/aside&gt;</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include a <strong><code>X-Api-Key</code></strong> header with the value <strong><code>"{MERCHANT_API_KEY}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>API keys are <strong>tenant-scoped</strong>: each key maps to exactly one merchant, and that merchant is the tenant for the request. Generate one with <code>php artisan billing:api-key --merchant={id}</code>. The dashboard endpoint additionally accepts an authenticated team-member session.</p>

        <h1 id="endpoints">Endpoints</h1>

    

                                <h2 id="endpoints-GETapi-plans">List the merchant&#039;s plans (active only by default).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-plans">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://mallow.test/api/plans" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/plans"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/plans';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/plans'
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('GET', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-GETapi-plans">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_api_key&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-plans" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-plans"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-plans" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-plans">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-plans" data-method="GET"
      data-path="api/plans"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-plans', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-plans"
                    onclick="tryItOut('GETapi-plans');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-plans"
                    onclick="cancelTryOut('GETapi-plans');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-plans"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="GETapi-plans"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-plans">Create a plan for the merchant.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-plans">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://mallow.test/api/plans" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"b\",
    \"base_price\": 22,
    \"included_units\": 7,
    \"overage_rate\": 16,
    \"billing_cycle_days\": 17,
    \"is_active\": true
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/plans"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "b",
    "base_price": 22,
    "included_units": 7,
    "overage_rate": 16,
    "billing_cycle_days": 17,
    "is_active": true
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/plans';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'name' =&gt; 'b',
            'base_price' =&gt; 22,
            'included_units' =&gt; 7,
            'overage_rate' =&gt; 16,
            'billing_cycle_days' =&gt; 17,
            'is_active' =&gt; true,
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/plans'
payload = {
    "name": "b",
    "base_price": 22,
    "included_units": 7,
    "overage_rate": 16,
    "billing_cycle_days": 17,
    "is_active": true
}
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-plans">
</span>
<span id="execution-results-POSTapi-plans" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-plans"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-plans"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-plans" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-plans">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-plans" data-method="POST"
      data-path="api/plans"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-plans', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-plans"
                    onclick="tryItOut('POSTapi-plans');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-plans"
                    onclick="cancelTryOut('POSTapi-plans');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-plans"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/plans</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="POSTapi-plans"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-plans"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-plans"
               value="b"
               data-component="body">
    <br>
<p>Must not be greater than 255 characters. Example: <code>b</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>base_price</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="base_price"                data-endpoint="POSTapi-plans"
               value="22"
               data-component="body">
    <br>
<p>Must be at least 0. Must not be greater than 99999999.99. Example: <code>22</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>included_units</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="included_units"                data-endpoint="POSTapi-plans"
               value="7"
               data-component="body">
    <br>
<p>Must be at least 0. Must not be greater than 18446744073709551615. Example: <code>7</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>overage_rate</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="overage_rate"                data-endpoint="POSTapi-plans"
               value="16"
               data-component="body">
    <br>
<p>Must be at least 0. Must not be greater than 9999.9999. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>billing_cycle_days</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="billing_cycle_days"                data-endpoint="POSTapi-plans"
               value="17"
               data-component="body">
    <br>
<p>Must be at least 1. Must not be greater than 65535. Example: <code>17</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <label data-endpoint="POSTapi-plans" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="POSTapi-plans"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-plans" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="POSTapi-plans"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Example: <code>true</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-customers">List the merchant&#039;s customers.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-customers">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://mallow.test/api/customers" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/customers"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/customers';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/customers'
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('GET', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-GETapi-customers">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_api_key&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-customers" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-customers"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-customers"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-customers" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-customers">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-customers" data-method="GET"
      data-path="api/customers"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-customers', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-customers"
                    onclick="tryItOut('GETapi-customers');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-customers"
                    onclick="cancelTryOut('GETapi-customers');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-customers"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/customers</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="GETapi-customers"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-customers"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-customers"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-subscriptions">Create a subscription for a merchant&#039;s customer.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-subscriptions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://mallow.test/api/subscriptions" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"customer_id\": 16,
    \"plan_id\": 16,
    \"starts_at\": \"2022-10-21\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/subscriptions"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "customer_id": 16,
    "plan_id": 16,
    "starts_at": "2022-10-21"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/subscriptions';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'customer_id' =&gt; 16,
            'plan_id' =&gt; 16,
            'starts_at' =&gt; '2022-10-21',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/subscriptions'
payload = {
    "customer_id": 16,
    "plan_id": 16,
    "starts_at": "2022-10-21"
}
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-subscriptions">
</span>
<span id="execution-results-POSTapi-subscriptions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-subscriptions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-subscriptions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-subscriptions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-subscriptions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-subscriptions" data-method="POST"
      data-path="api/subscriptions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-subscriptions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-subscriptions"
                    onclick="tryItOut('POSTapi-subscriptions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-subscriptions"
                    onclick="cancelTryOut('POSTapi-subscriptions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-subscriptions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/subscriptions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="POSTapi-subscriptions"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-subscriptions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-subscriptions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>customer_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="customer_id"                data-endpoint="POSTapi-subscriptions"
               value="16"
               data-component="body">
    <br>
<p>Must match an existing stored value. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>plan_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="plan_id"                data-endpoint="POSTapi-subscriptions"
               value="16"
               data-component="body">
    <br>
<p>Must match an existing stored value. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>starts_at</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="starts_at"                data-endpoint="POSTapi-subscriptions"
               value="2022-10-21"
               data-component="body">
    <br>
<p>Must be a valid date. Must be a date before or equal to <code>today</code>. Example: <code>2022-10-21</code></p>
        </div>
        </form>

                    <h2 id="endpoints-PATCHapi-subscriptions--subscription_id--plan">Change the subscription&#039;s plan (mid-cycle safe).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PATCHapi-subscriptions--subscription_id--plan">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PATCH \
    "http://mallow.test/api/subscriptions/1/plan" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"plan_id\": 16,
    \"effective_date\": \"2026-09-26T16:44:15\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/subscriptions/1/plan"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "plan_id": 16,
    "effective_date": "2026-09-26T16:44:15"
};

fetch(url, {
    method: "PATCH",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/subscriptions/1/plan';
$response = $client-&gt;patch(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'plan_id' =&gt; 16,
            'effective_date' =&gt; '2026-09-26T16:44:15',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/subscriptions/1/plan'
payload = {
    "plan_id": 16,
    "effective_date": "2026-09-26T16:44:15"
}
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('PATCH', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-PATCHapi-subscriptions--subscription_id--plan">
</span>
<span id="execution-results-PATCHapi-subscriptions--subscription_id--plan" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PATCHapi-subscriptions--subscription_id--plan"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PATCHapi-subscriptions--subscription_id--plan"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PATCHapi-subscriptions--subscription_id--plan" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PATCHapi-subscriptions--subscription_id--plan">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PATCHapi-subscriptions--subscription_id--plan" data-method="PATCH"
      data-path="api/subscriptions/{subscription_id}/plan"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PATCHapi-subscriptions--subscription_id--plan', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PATCHapi-subscriptions--subscription_id--plan"
                    onclick="tryItOut('PATCHapi-subscriptions--subscription_id--plan');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PATCHapi-subscriptions--subscription_id--plan"
                    onclick="cancelTryOut('PATCHapi-subscriptions--subscription_id--plan');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PATCHapi-subscriptions--subscription_id--plan"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/subscriptions/{subscription_id}/plan</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="PATCHapi-subscriptions--subscription_id--plan"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PATCHapi-subscriptions--subscription_id--plan"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PATCHapi-subscriptions--subscription_id--plan"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>subscription_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="subscription_id"                data-endpoint="PATCHapi-subscriptions--subscription_id--plan"
               value="1"
               data-component="url">
    <br>
<p>The ID of the subscription. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>plan_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="plan_id"                data-endpoint="PATCHapi-subscriptions--subscription_id--plan"
               value="16"
               data-component="body">
    <br>
<p>Must match an existing stored value. Example: <code>16</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>effective_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="effective_date"                data-endpoint="PATCHapi-subscriptions--subscription_id--plan"
               value="2026-09-26T16:44:15"
               data-component="body">
    <br>
<p>Must be a valid date. Example: <code>2026-09-26T16:44:15</code></p>
        </div>
        </form>

                    <h2 id="endpoints-POSTapi-subscriptions--subscription_id--invoice">Generate the invoice for a billing period (current cycle by default).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Runs the action synchronously for immediate feedback; the queued job
exists for batch/scheduled generation (see GenerateInvoiceJob).</p>

<span id="example-requests-POSTapi-subscriptions--subscription_id--invoice">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://mallow.test/api/subscriptions/1/invoice" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"period_start\": \"2026-09-26T16:44:15\",
    \"period_end\": \"2052-10-19\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/subscriptions/1/invoice"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "period_start": "2026-09-26T16:44:15",
    "period_end": "2052-10-19"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/subscriptions/1/invoice';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'period_start' =&gt; '2026-09-26T16:44:15',
            'period_end' =&gt; '2052-10-19',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/subscriptions/1/invoice'
payload = {
    "period_start": "2026-09-26T16:44:15",
    "period_end": "2052-10-19"
}
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-subscriptions--subscription_id--invoice">
</span>
<span id="execution-results-POSTapi-subscriptions--subscription_id--invoice" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-subscriptions--subscription_id--invoice"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-subscriptions--subscription_id--invoice"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-subscriptions--subscription_id--invoice" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-subscriptions--subscription_id--invoice">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-subscriptions--subscription_id--invoice" data-method="POST"
      data-path="api/subscriptions/{subscription_id}/invoice"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-subscriptions--subscription_id--invoice', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-subscriptions--subscription_id--invoice"
                    onclick="tryItOut('POSTapi-subscriptions--subscription_id--invoice');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-subscriptions--subscription_id--invoice"
                    onclick="cancelTryOut('POSTapi-subscriptions--subscription_id--invoice');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-subscriptions--subscription_id--invoice"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/subscriptions/{subscription_id}/invoice</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="POSTapi-subscriptions--subscription_id--invoice"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-subscriptions--subscription_id--invoice"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-subscriptions--subscription_id--invoice"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>subscription_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="subscription_id"                data-endpoint="POSTapi-subscriptions--subscription_id--invoice"
               value="1"
               data-component="url">
    <br>
<p>The ID of the subscription. Example: <code>1</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>period_start</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="period_start"                data-endpoint="POSTapi-subscriptions--subscription_id--invoice"
               value="2026-09-26T16:44:15"
               data-component="body">
    <br>
<p>This field is required when <code>period_end</code> is present. Must be a valid date. Example: <code>2026-09-26T16:44:15</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>period_end</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="period_end"                data-endpoint="POSTapi-subscriptions--subscription_id--invoice"
               value="2052-10-19"
               data-component="body">
    <br>
<p>This field is required when <code>period_start</code> is present. Must be a valid date. Must be a date after <code>period_start</code>. Example: <code>2052-10-19</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-merchants--id--dashboard">Merchant dashboard metrics (Plan §5.2).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Dual auth (D4.2): X-Api-Key resolves the merchant directly; otherwise a
session-authenticated user must belong to the target team.</p>

<span id="example-requests-GETapi-merchants--id--dashboard">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://mallow.test/api/merchants/564/dashboard" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/merchants/564/dashboard"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};


fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/merchants/564/dashboard';
$response = $client-&gt;get(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/merchants/564/dashboard'
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('GET', url, headers=headers)
response.json()</code></pre></div>

</span>

<span id="example-responses-GETapi-merchants--id--dashboard">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
set-cookie: mallow-session=eyJpdiI6IllybzJrTU55Z05wN3ZhT0h0Q095ZGc9PSIsInZhbHVlIjoiMkJ1NGVaUVBiR3g4RDFaTWg3N0U4bEhGYityV3A3T250M3gyYkpCL1J1RUhiZ3U2bGVVUmxCOWwwWFJ5WWFSTGNHdkhlcDdOaGNhNlRkMXlHT2xjSHh3VDVVK2JnN1R3Vjk2ZDkxZDlWV3lNRFlSUjhqdFlOTk9Wb1cvclpwT1EiLCJtYWMiOiI3Yjk5MTcyODY4NDlmNGQ1MjI1MTdmYThlY2E3YjJjYWY2OWFmMTAxYzJkMDM4YTVhODI3ZmJiMjljODAyNGY2IiwidGFnIjoiIn0%3D; expires=Sat, 26 Sep 2026 18:44:15 GMT; Max-Age=7200; path=/; httponly; samesite=lax
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;invalid_api_key&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-merchants--id--dashboard" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-merchants--id--dashboard"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-merchants--id--dashboard"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-merchants--id--dashboard" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-merchants--id--dashboard">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-merchants--id--dashboard" data-method="GET"
      data-path="api/merchants/{id}/dashboard"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-merchants--id--dashboard', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-merchants--id--dashboard"
                    onclick="tryItOut('GETapi-merchants--id--dashboard');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-merchants--id--dashboard"
                    onclick="cancelTryOut('GETapi-merchants--id--dashboard');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-merchants--id--dashboard"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/merchants/{id}/dashboard</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="GETapi-merchants--id--dashboard"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-merchants--id--dashboard"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-merchants--id--dashboard"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-merchants--id--dashboard"
               value="564"
               data-component="url">
    <br>
<p>The ID of the merchant. Example: <code>564</code></p>
            </div>
                    </form>

                <h1 id="usage">Usage</h1>

    

                                <h2 id="usage-POSTapi-usage">Record a usage event for the authenticated merchant&#039;s customer (idempotent).</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>The merchant (tenant) is resolved from the X-Api-Key header; the customer
must belong to that merchant and hold an active subscription covering
usage_date. Retrying with the same idempotency_key returns
200 {duplicate: true} and never counts the usage twice.</p>

<span id="example-requests-POSTapi-usage">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://mallow.test/api/usage" \
    --header "X-Api-Key: {MERCHANT_API_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"customer_id\": 1,
    \"usage_date\": \"2026-09-26\",
    \"quantity\": 250,
    \"type\": \"api_calls\",
    \"idempotency_key\": \"0d0f4c2e-1111-4111-8111-000000000001\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://mallow.test/api/usage"
);

const headers = {
    "X-Api-Key": "{MERCHANT_API_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "customer_id": 1,
    "usage_date": "2026-09-26",
    "quantity": 250,
    "type": "api_calls",
    "idempotency_key": "0d0f4c2e-1111-4111-8111-000000000001"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>


<div class="php-example">
    <pre><code class="language-php">$client = new \GuzzleHttp\Client();
$url = 'http://mallow.test/api/usage';
$response = $client-&gt;post(
    $url,
    [
        'headers' =&gt; [
            'X-Api-Key' =&gt; '{MERCHANT_API_KEY}',
            'Content-Type' =&gt; 'application/json',
            'Accept' =&gt; 'application/json',
        ],
        'json' =&gt; [
            'customer_id' =&gt; 1,
            'usage_date' =&gt; '2026-09-26',
            'quantity' =&gt; 250,
            'type' =&gt; 'api_calls',
            'idempotency_key' =&gt; '0d0f4c2e-1111-4111-8111-000000000001',
        ],
    ]
);
$body = $response-&gt;getBody();
print_r(json_decode((string) $body));</code></pre></div>


<div class="python-example">
    <pre><code class="language-python">import requests
import json

url = 'http://mallow.test/api/usage'
payload = {
    "customer_id": 1,
    "usage_date": "2026-09-26",
    "quantity": 250,
    "type": "api_calls",
    "idempotency_key": "0d0f4c2e-1111-4111-8111-000000000001"
}
headers = {
  'X-Api-Key': '{MERCHANT_API_KEY}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}

response = requests.request('POST', url, headers=headers, json=payload)
response.json()</code></pre></div>

</span>

<span id="example-responses-POSTapi-usage">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{&quot;duplicate&quot;: true} {&quot;Duplicate idempotency_key: the event was already stored; nothing is counted twice.&quot;}</code>
 </pre>
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;customer_id&quot;: 1,
        &quot;usage_date&quot;: &quot;2026-09-26&quot;,
        &quot;quantity&quot;: 250,
        &quot;type&quot;: &quot;api_calls&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{&quot;message&quot;: &quot;Not found.&quot;} {&quot;Customer does not belong to this merchant.&quot;}</code>
 </pre>
            <blockquote>
            <p>Example response (422):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{&quot;message&quot;: &quot;...&quot;, &quot;errors&quot;: {&quot;customer_id&quot;: [&quot;no_active_subscription&quot;]}} {&quot;Customer has no active subscription covering usage_date.&quot;}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-usage" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-usage"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-usage"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-usage" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-usage">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-usage" data-method="POST"
      data-path="api/usage"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-usage', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-usage"
                    onclick="tryItOut('POSTapi-usage');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-usage"
                    onclick="cancelTryOut('POSTapi-usage');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-usage"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/usage</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>X-Api-Key</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="X-Api-Key" class="auth-value"               data-endpoint="POSTapi-usage"
               value="{MERCHANT_API_KEY}"
               data-component="header">
    <br>
<p>Example: <code>{MERCHANT_API_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-usage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-usage"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>customer_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="customer_id"                data-endpoint="POSTapi-usage"
               value="1"
               data-component="body">
    <br>
<p>The customer ID (must belong to the authenticated merchant). Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>usage_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="usage_date"                data-endpoint="POSTapi-usage"
               value="2026-09-26"
               data-component="body">
    <br>
<p>The usage date, today or earlier (Y-m-d). Example: <code>2026-09-26</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>quantity</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="quantity"                data-endpoint="POSTapi-usage"
               value="250"
               data-component="body">
    <br>
<p>Usage quantity, min 1. Example: <code>250</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="POSTapi-usage"
               value="api_calls"
               data-component="body">
    <br>
<p>Usage type (alpha-dash), informational — all types bill against one allowance. Example: <code>api_calls</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>idempotency_key</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="idempotency_key"                data-endpoint="POSTapi-usage"
               value="0d0f4c2e-1111-4111-8111-000000000001"
               data-component="body">
    <br>
<p>UUID v4. Retries with the same key are safe. Example: <code>0d0f4c2e-1111-4111-8111-000000000001</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                                                        <button type="button" class="lang-button" data-language-name="php">php</button>
                                                        <button type="button" class="lang-button" data-language-name="python">python</button>
                            </div>
            </div>
</div>
</body>
</html>
