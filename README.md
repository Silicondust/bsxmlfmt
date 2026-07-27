# bsxmlfmt
**XML formatter for BrightScript that preserves multi-line formatting**

Removes unnecessary whitespace within *existing* lines.

Indentation follows the BrightScript convention of 4-space tabs.

Blank lines are removed.

**Usage:**

php bsxmlfmt.php \<filename\>

**To update all XML files:**

find . -name \\*.xml -exec php bsxmlfmt.php {} \\;
