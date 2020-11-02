<input $AttributesHTML 
    <% if $AddressInputFields %> data-addressfields=
    '[<% loop $AddressInputFields %>"{$Me.value}"<% if not Last %>,<% end_if %><% end_loop %>]'
    <% end_if %>
    <% if $LocationPickerOptions %> data-locationpickeroptions=
    '{<% loop $LocationPickerOptions %>"{$Me.key}": "{$Me.value}"<% if not Last %>,<% end_if %><% end_loop %>}'
    <% end_if %>
    />