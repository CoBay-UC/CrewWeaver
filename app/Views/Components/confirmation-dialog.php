<?php declare(strict_types=1); ?>
<dialog class="cw-dialog cw-dialog-confirm" id="confirmationDialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-message">
    <form method="dialog">
        <div class="cw-dialog-header"><h2 class="h5 mb-0" id="confirmation-title"></h2></div>
        <div class="cw-dialog-body">
            <p class="mb-0" id="confirmation-message"></p>
            <fieldset class="mt-3" data-confirm-typed hidden disabled>
                <label class="form-label" for="confirmation-input">Type this sentence to confirm</label>
                <p class="small fw-semibold mb-2" id="confirmation-phrase"></p>
                <input class="form-control" id="confirmation-input" aria-describedby="confirmation-phrase"
                       required autocomplete="off" spellcheck="false">
            </fieldset>
        </div>
        <div class="cw-dialog-footer">
            <button type="submit" class="btn btn-outline-secondary" value="cancel" formnovalidate autofocus>Cancel</button>
            <button type="submit" class="btn" value="confirm" data-confirm-action></button>
        </div>
    </form>
</dialog>
