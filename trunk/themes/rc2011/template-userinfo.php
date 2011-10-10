<?php
/**
 * Template Name: user info page
 *
 * displays user info from the arena plugin
 */

get_header(); ?>

		<div id="primary">
			<div id="content" role="main">

				<?php the_post(); ?>

				<?php 
					$user = wp_get_current_user();
					if ($user && is_user_logged_in()) {
						echo '<span id="userinfo">Hi, '.$user->display_name.' &mdash; how are you today?</span>'."&nbsp;\n";
						echo '<a href="'. wp_logout_url( get_permalink() ) .'" title="Logout">Logout</a>' ."\n";
						get_template_part( 'content', 'page' );
						?>
						<div id="familywrapper">					
						<?php						
						if (function_exists('call_arena')) {
							$familyXml = call_arena("get", "family/". $user->arena_info['familyId']); ?>
							<div id="family">
							<h2>The <?php echo $familyXml->FamilyName; ?></h2>
							<div class="left">
								<?php get_avatar($user->id); ?>
							</div>
							<table class="right"><thead><tr>
								<th>ID</th><th>Name</th><th>Role</th>
							</tr></thead>
							<tbody>
							<?php
							foreach ($familyXml->FamilyMembers->Person as $p) { ?>
								<tr>
									<td><?php echo $p->PersonID; ?></td>
									<td><?php echo $p->FullName; ?></td>
									<td><?php echo $p->FamilyMemberRoleValue; ?></td>
								</tr>
							<?php } ?>
							</tbody>
							</table>
							<div class="clear">&nbsp;</div>
							</div>
						</div>
							<?php
						}						
						
					} else {
						echo '<p>you must <a href="'. wp_login_url( get_permalink() ) .'" title="Login">Login</a> to see this page</p>'."\n";
					}
				 ?>

			</div><!-- #content -->
		</div><!-- #primary -->

<?php get_footer(); ?>