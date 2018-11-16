<?php

namespace ShinyBear\Modules;

/**
 * @package ShinyBear
 * @since 1.0
 */
class search extends Module
{
	public function output()
	{
	global $scripturl, $txt, $context;

	echo '
							<div class="centertext">
								<form action="', $scripturl, '?action=search2" method="post" accsbt-charset="', $context['character_set'], '" name="searchform" id="searchform">
								<div class="centertext" style="margin-top: -5px;"><input name="search" size="18" maxlength="100" tabindex="', $context['tabindex']++, '" type="text" class="input_text" /></div>

								<script type="text/javascript"><!-- // --><![CDATA[
									function initSearch()
									{
										if (document.forms.searchform.search.value.indexOf("%u") != -1)
											document.forms.searchform.search.value = unescape(document.forms.searchform.search.value);
									}
									createEventListener(window);
									window.addEventListener("load", initSearch, false);
								// ]]></script>

								<select name="searchtype" tabindex="', $context['tabindex']++, '" style="margin: 5px 5px 0 0;">
									<option value="1" selected="selected">', $txt['sb_match_all_words'], '</option>
									<option value="2">', $txt['sb_match_any_words'], '</option>
								</select><input style="margin-top: 5px;" name="submit" value="', $txt['search'], '" tabindex="', $context['tabindex']++, '" type="submit" class="button_submit" />
								</form>
							</div>';
}
}
