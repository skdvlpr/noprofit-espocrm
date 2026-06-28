<div
    class="link-container list-group{{#if keepItems}} no-input{{/if}}"
>{{#each itemHtmlList}}{{{./this}}}{{/each}}</div>
<div class="array-control-container">
{{#if allowCustomOptions}}
<div class="input-group">
    <input
        class="main-element form-control select numeric-text"
        type="text"
        autocomplete="off"
        placeholder="{{translate 'typeAndPressEnter' category='messages'}}"
        {{#if maxItemLength}} maxlength="{{maxItemLength}}"{{/if}}
    >
    <span class="input-group-btn">
        <button
            type="button"
            class="btn btn-default btn-icon date-picker-btn"
            tabindex="-1"
            title="{{translate 'Select' category='labels' scope='Global'}}"
        ><i class="far fa-calendar"></i></button>
    </span>
    <span class="input-group-btn">
        <button
            data-action="addItem"
            class="btn btn-default btn-icon"
            type="button"
            tabindex="-1"
            title="{{translate 'Add Item'}}"
        ><span class="fas fa-plus"></span></button>
    </span>
</div>
{{/if}}
</div>
