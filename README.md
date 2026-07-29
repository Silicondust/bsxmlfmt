# bsxmlfmt
**XML formatter that preserves multi-line formatting**

Removes unnecessary whitespace within *existing* lines while preserving the original layout and minimizing diffs.

Doesn't split existing lines into multiple lines. For example comments on the same line are preserved.

Doesn't combine existing lines into one line. For example any existing one-attribute-per-line formatting is preserved.

Fixes indentation. Indentation can be set to 'tab' or a specified number of spaces.

Fixes mid-line whitespace. Removes end-of-line whitespace.

Empty lines are removed.

Originally written for Roku BrightScript XML files. Supports all XML files.

**Usage examples:**

php bsxmlfmt.php --indent 4 \<filename\>

php bsxmlfmt.php --indent tab \<filename\>

**To update all XML files:**

find . -name \\*.xml -exec php bsxmlfmt.php --indent 4 {} \\;
