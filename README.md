# bsxmlfmt
**XML formatter for BrightScript that preserves multi-line formatting**

Removes unnecessary whitespace within *existing* lines while preserving the original layout and minimizing diffs.

Indentation can be set to 'tab' or a specified number of spaces.

Blank lines are removed.

**Usage examples:**

php bsxmlfmt.php --indent 4 \<filename\>
php bsxmlfmt.php --indent tab \<filename\>

**To update all XML files:**

find . -name \\*.xml -exec php bsxmlfmt.php --indent 4 {} \\;
