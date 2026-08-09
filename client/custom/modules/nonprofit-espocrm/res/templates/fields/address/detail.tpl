{{#if addressLines}}
{{#each addressLines}}
<div class="address-line">{{./this}}</div>
{{/each}}
{{else}}
{{#if formattedAddress}}
{{breaklines formattedAddress}}
{{/if}}
{{/if}}

{{#if isNone}}
<span class="none-value">{{translate 'None'}}</span>
{{/if}}

{{#if isLoading}}
<span class="loading-value"></span>
{{/if}}

{{#if viewMap}}
<div class="address-view-map-link"><a
    href="{{viewMapLink}}"
    data-action="viewMap"
    class="small"
    style="user-select: none;"
>{{translate 'View on Map'}}</a></div>
{{/if}}
