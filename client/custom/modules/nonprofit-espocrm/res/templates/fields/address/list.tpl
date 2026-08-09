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
