<tr>
    <th scope="row">
        Bulk convert
        <?php
            echo helpIcon(
                'Click the button to open dialog for bulk converting. PS: The bulk conversion is using the last saved settings.</p>'
            );
        ?>
    </th>
    <td>
        <div>
            <button onclick="openBulkConvertPopup()" class="button button-secondary" type="button">Bulk Convert</button>
            <div id="bulkconvertpopup" style="display:none;">
                <div id="bulkconvertcontent"></div>
            </div>
            <button onclick="openDeleteConvertedFilesPopup()" class="button button-secondary" type="button">Delete converted files</button>
        </div>
    </td>
</tr>