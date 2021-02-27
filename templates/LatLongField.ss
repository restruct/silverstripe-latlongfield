<div class="input-group latlong-fieldgroup">
    <div class="input-group-prepend">
        <input class="btn btn-outline-secondary btn-latlong btn-latlong-search" type="button" value="🔍" title="Search">
    </div>
    <input $AttributesHTML
        <% if $AddressInputFields %> data-addressfields='[<% loop $AddressInputFields %>"{$Me.value}"<% if not Last %>,<% end_if %><% end_loop %>]'<% end_if %>
        <% if $LocationPickerOptions %> data-locationpickeroptions='{<% loop $LocationPickerOptions %>"{$Me.key}": "{$Me.value}"<% if not Last %>,<% end_if %><% end_loop %>}'<% end_if %>
    />
    <div class="input-group-append">
        <input class="btn btn-outline-secondary btn-latlong btn-latlong-clear font-weight-bold" type="button" value="×" title="Clear"><%-- 🗑 --%>
    </div>
</div>