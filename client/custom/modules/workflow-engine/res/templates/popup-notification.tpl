{{#if closeButton~}}
    <a
        role="button"
        tabindex="0"
        class="pull-right close"
        data-action="close"
        aria-hidden="true"
        title="{{translate 'Close'}}"
    ><span class="fas fa-times"></span></a>
{{~/if~}}
{{#if collapseButton~}}
    <a
        role="button"
        tabindex="0"
        class="pull-right text-muted"
        data-action="collapse"
        aria-hidden="true"
        title="{{translate 'Collapse'}}"
    ><span class="fas fa-minus"></span></a>
{{~/if}}
<h4>{{header}}</h4>

<div class="cell form-group">
    <div class="field">{{message}}</div>
</div>

{{#if hasRelated}}
<div class="cell form-group">
    <div class="field">
        <a
            href="#{{relatedType}}/view/{{relatedId}}"
            data-action="close"
        >{{relatedName}}</a>
    </div>
</div>
{{/if}}
