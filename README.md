# bsxmlfmt
**XML formatter that preserves multi-line formatting**

Removes unnecessary whitespace within *existing* lines while preserving the original layout and minimizing diffs.

Indentation can be set to 'tab' or a specified number of spaces.

Empty lines are removed.

Originally written for Roku BrightScript XML files. Supports all XML files.

**Usage examples:**

php bsxmlfmt.php --indent 4 \<filename\>

php bsxmlfmt.php --indent tab \<filename\>

**To update all XML files:**

find . -name \\*.xml -exec php bsxmlfmt.php --indent 4 {} \\;
