<%-- Bootstrap 4 markup (prepend/append wrappers, font-weight-bold) for the SS5 CMS, flat Bootstrap 5 markup (fw-bold) for the SS6 CMS: see LatLongField::getUsesBootstrap4InputGroup() --%>
<div class="input-group latlong-fieldgroup">
    <% if $UsesBootstrap4InputGroup %><div class="input-group-prepend"><% end_if %>
        <input class="btn btn-outline-secondary btn-latlong btn-latlong-search" type="button" value="🔍" title="Search">
    <% if $UsesBootstrap4InputGroup %></div><% end_if %>
    <input $AttributesHTML data-addressfields='$AddressInputFieldsJSON' data-locationpickeroptions='$LocationPickerOptionsJSON' />
    <% if $UsesBootstrap4InputGroup %><div class="input-group-append"><% end_if %>
        <input class="btn btn-outline-secondary btn-latlong btn-latlong-clear <% if $UsesBootstrap4InputGroup %>font-weight-bold<% else %>fw-bold<% end_if %>" type="button" value="×" title="Clear"><%-- 🗑 --%>
    <% if $UsesBootstrap4InputGroup %></div><% end_if %>
</div>
