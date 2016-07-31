<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title><?php echo $report[ 'ok' ] ? 'OK' : 'FAILED' ?> | LessQL Runner</title>
		<link rel="stylesheet" src="https://cdnjs.cloudflare.com/ajax/libs/normalize/4.2.0/normalize.min.css">
		<style type="text/css">
			table {
				width: 100%;
			}

			td, th {
				text-align: left;
			}

			#container {
				max-width: 800px;
				margin: 0 auto;
				font-family: Helvetica, Sans-Serif;
			}

			#footer {
				margin: 50px 0;
			}

			@media ( min-width: 640px ) {
				#info {
					overflow: hidden;
				}

				#log {
					float: left;
					width: 45%;
				}

				#history {
					margin-left: 55%;
				}
			}
		</style>
	</head>
	<body>
		<div id="container">
			<header id="header">
				<h1>LessQL Runner</h1>
				<h2 class="status"><?php echo $report[ 'ok' ] ? 'OK' : 'FAILED' ?></h2>
			</header>

			<div id="info">
				<div id="log">
					<h3>Log</h3>
					<table>
						<tr>
							<th>Transaction</th>
							<th>Message</th>
						</tr>
						<?php foreach ( $report[ 'log' ] as $item ): ?>
							<tr>
								<td><?php echo $item[ 'id' ] ?></td>
								<td><?php echo $item[ 'message' ] ?></td>
							</tr>
						<?php endforeach ?>
					</table>
				</div>

				<div id="history">
					<h3>History</h3>
					<table>
						<tr>
							<th>Transaction</th>
							<th>Execution Time</th>
						</tr>
						<?php foreach ( $report[ 'history' ] as $item ): ?>
							<tr>
								<td><?php echo $item[ 'id' ] ?></td>
								<td><?php
									list( $u, $s ) = explode( ' ', $item[ 'executed' ] );
									echo date( 'Y-m-d H:i:s', $s );
								?></td>
							</tr>
						<?php endforeach ?>
					</table>
				</div>
			</div>

			<footer id="footer">
				LessQL Runner Version <?php echo $report[ 'version' ] ?>
			</footer>
		</div>
	</body>
</html>
