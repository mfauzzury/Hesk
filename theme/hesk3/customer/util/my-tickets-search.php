<?php
// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

function displayMyTicketsSearch($searchType, $searchCriteria, $ticketListQuery) {
    global $hesklang;
    ?>

        <form action="my_tickets.php" method="get" aria-label="<?php echo $hesklang['customer_my_tickets_search_for_tickets']; ?>" style="display: inline; margin: 0;" name="searchform">
            <div class="search__form">
                <div class="form-group">
                    <button class="btn search__submit" aria-label="<?php echo $hesklang['customer_my_tickets_search_for_tickets']; ?>">
                        <svg class="icon icon-search">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-search"></use>
                        </svg>
                    </button>
                    <input id="kb_search" name="search" class="form-control" type="text"
                           placeholder="<?php echo $hesklang['customer_my_tickets_search_for_tickets']; ?>"
                           aria-label="<?php echo $hesklang['customer_my_tickets_search_for_tickets']; ?>"
                           value="<?php echo stripslashes($searchCriteria); ?>">
                    <select name="search-by" id="search-by">
                        <option value="trackid" <?php if ($searchType === 'trackid') { echo 'selected'; } ?>><?php echo $hesklang['trackID']; ?></option>
                        <option value="subject" <?php if ($searchType === 'subject') { echo 'selected'; } ?>><?php echo $hesklang['subject']; ?></option>
                        <option value="message" <?php if ($searchType === 'message') { echo 'selected'; } ?>><?php echo $hesklang['message']; ?></option>
                    </select>
                    <button id="search-button" type="submit" class="btn btn-full"><?php echo $hesklang['search']; ?></button>
                </div>
            </div>
            <div class="radio-list" role="group" aria-label="<?php echo $hesklang['status']; ?>" style="display: flex; flex-wrap: wrap; gap: 20px; margin: 16px 0;">
                <?php foreach (array('ALL' => $hesklang['all'], 'OPEN' => $hesklang['open_tickets'], 'CLOSED' => $hesklang['closed']) as $value => $label): ?>
                    <div class="radio-custom">
                        <input type="radio" name="status" id="ticket-status-<?php echo $value; ?>" value="<?php echo $value; ?>" <?php if ($ticketListQuery['status'] === $value) { echo 'checked'; } ?> onchange="this.form.submit()">
                        <label for="ticket-status-<?php echo $value; ?>"><?php echo $label; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php foreach (array('order-by', 'order-direction', 'page-size') as $parameter): ?>
                <input type="hidden" name="<?php echo $parameter; ?>" value="<?php echo hesk_htmlspecialchars($ticketListQuery[$parameter]); ?>">
            <?php endforeach; ?>
        </form>
    <?php
}

function outputSearchJavascript() {
    global $hesklang;
    ?>
    <script>
        $('#search-by').selectize({
            onInitialize: function () {
                var $input = this.$control; // Selectize input container
                $input.attr({
                    "role": "combobox",
                    "aria-expanded": "false",
                    "aria-label": "<?php echo $hesklang['wsel']; ?>", // Adjust label as needed
                    "aria-controls": this.$dropdown.attr("id"), // Links input to dropdown
                    "tabindex": "0" // Ensure it is focusable
                });
                this.$dropdown.attr("role", "listbox"); // Dropdown should be a listbox
                this.$dropdown.find("[data-value]").attr("role", "option"); // Each item is an option
            },
            onDropdownOpen: function ($dropdown) {
                this.$control.attr("aria-expanded", "true");
            },
            onDropdownClose: function ($dropdown) {
                this.$control.attr("aria-expanded", "false");
            }
        });
    </script>
    <?php
}