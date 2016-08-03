<?php
	$jsFile = 'dashboard';

	// Start/Stop the Time Clock
	if (isset($_POST['submit']) && $_POST['submit'] == 'toggleTime') {
		$isRecord = $mysqli->real_escape_string($_POST['isRecord']);

		if ($isRecord != '0') {
			// Record All Ready Exists
			$clockId = $mysqli->real_escape_string($_POST['clockId']);
			$entryId = $mysqli->real_escape_string($_POST['entryId']);
			$weekNo = $mysqli->real_escape_string($_POST['weekNo']);
			$clockYear = $mysqli->real_escape_string($_POST['clockYear']);
			$running = $mysqli->real_escape_string($_POST['running']);
			$entryDate = $endTime = date("Y-m-d");
			$startTime = $endTime = date("Y-m-d H:i:s");

			if ($running == '0') {
				// Start Clock - Update the timeclock Record
				$sqlstmt = $mysqli->prepare("
									UPDATE
										timeclock
									SET
										running = 1
									WHERE
										clockId = ?
				");
				$sqlstmt->bind_param('s',$clockId);
				$sqlstmt->execute();
				$sqlstmt->close();

				// Start Clock - Add a new time entry
				$stmt = $mysqli->prepare("
									INSERT INTO
										timeentry(
											clockId,
											empId,
											entryDate,
											startTime
										) VALUES (
											?,
											?,
											?,
											?
										)
				");
				$stmt->bind_param('ssss',
									$clockId,
									$empId,
									$entryDate,
									$startTime
				);
				$stmt->execute();
				$stmt->close();
			} else {
				// Stop Clock - Update the timeclock Record
				$sqlstmt = $mysqli->prepare("
									UPDATE
										timeclock
									SET
										running = 0
									WHERE
										clockId = ?
				");
				$sqlstmt->bind_param('s',$clockId);
				$sqlstmt->execute();
				$sqlstmt->close();

				// Stop Clock - Update the time entry
				$stmt = $mysqli->prepare("
									UPDATE
										timeentry
									SET
										endTime = ?
									WHERE
										entryId = ?
				");
				$stmt->bind_param('ss',
									$endTime,
									$entryId
				);
				$stmt->execute();
				$stmt->close();
			}
		} else {
			// Record Does Not Exist
			// Start Clock - Create a timeclock Record
			$weekNo = $mysqli->real_escape_string($_POST['weekNo']);
			$clockYear = $mysqli->real_escape_string($_POST['clockYear']);
			$running = '1';
			$startTime = date("Y-m-d H:i:s");

			$sqlstmt = $mysqli->prepare("
								INSERT INTO
									timeclock(
										empId,
										weekNo,
										clockYear,
										running
									) VALUES (
										?,
										?,
										?,
										?
									)
			");
			$sqlstmt->bind_param('ssss',
									$empId,
									$weekNo,
									$clockYear,
									$running
			);
			$sqlstmt->execute();
			$sqlstmt->close();

			// Get the new Tracking ID
			$track_id = $mysqli->query("SELECT clockId FROM timeclock WHERE empId = ".$empId." AND weekNo = '".$weekNo."' AND clockYear = ".$currentYear);
			$id = mysqli_fetch_assoc($track_id);
			$clockId = $id['clockId'];
			$entryDate = $endTime = date("Y-m-d");

			// Start Clock - Add a new time entry
			$stmt = $mysqli->prepare("
								INSERT INTO
									timeentry(
										clockId,
										empId,
										entryDate,
										startTime
									) VALUES (
										?,
										?,
										?,
										?
									)
			");
			$stmt->bind_param('ssss',
								$clockId,
								$empId,
								$entryDate,
								$startTime
			);
			$stmt->execute();
			$stmt->close();
		}
	}

	// Check for an Existing Record
	$check = $mysqli->query("SELECT 'X' FROM timeclock WHERE empId = ".$empId." AND weekNo = '".$weekNum."'");
	if ($check->num_rows) {
		$checked = "SELECT
						clockId,
						empId,
						weekNo,
						clockYear,
						running
					FROM
						timeclock
					WHERE
						empId = ".$empId." AND weekNo = '".$weekNum."'";
		$checkres = mysqli_query($mysqli, $checked) or die('-1'.mysqli_error());
		$col = mysqli_fetch_assoc($checkres);
		$clockId = $col['clockId'];
		$running = $col['running'];

		$sel = "SELECT
					clockId,
					entryId
				FROM
					timeentry
				WHERE
					clockId = ".$clockId." AND
					empId = ".$empId." AND
					endTime = '0000-00-00'";
		$selresult = mysqli_query($mysqli, $sel) or die('-2'.mysqli_error());
		$rows = mysqli_fetch_assoc($selresult);
		$entryId = (is_null($rows['entryId'])) ? '' : $rows['entryId'];
		$isRecord = '1';

		// Get Total Time Worked for the Current Week
		$qry1 = "SELECT
					TIMEDIFF(timeentry.endTime,timeentry.startTime) AS diff
				FROM
					timeclock
					LEFT JOIN timeentry ON timeclock.clockId = timeentry.clockId
				WHERE
					timeclock.empId = ".$empId." AND
					timeclock.weekNo = '".$weekNum."' AND
					timeclock.clockYear = '".$currentYear."' AND
					timeentry.endTime != '0000-00-00 00:00:00'";
		$results = mysqli_query($mysqli, $qry1) or die('-3'.mysqli_error());
		$times = array();
		while ($u = mysqli_fetch_assoc($results)) {
			$times[] = $u['diff'];
		}
		$totalTime = sumHours($times);
	} else {
		$clockId = '';
		$entryId = '';
		$running = $isRecord = '0';
		$totalTime = '00:00:00';
	}

	// Get Unread Message Count
	$unreadsql = "SELECT 'X' FROM privatemessages WHERE toId = ".$empId." AND toRead = 0";
	$unreadtotal = mysqli_query($mysqli, $unreadsql) or die('-4'.mysqli_error());
	$unread = mysqli_num_rows($unreadtotal);

	// Get Notice Data
    $sqlSmt  = "SELECT
					notices.createdBy,
					notices.isActive,
					notices.noticeTitle,
					notices.noticeText,
					DATE_FORMAT(notices.noticeDate,'%M %d, %Y') AS noticeDate,
					UNIX_TIMESTAMP(notices.noticeDate) AS orderDate,
					notices.noticeStart,
					notices.noticeExpires,
					CONCAT(employees.empFirst,' ',employees.empLast) AS postedBy
				FROM
					notices
					LEFT JOIN employees ON notices.createdBy = employees.empId
				WHERE
					notices.noticeStart <= DATE_SUB(CURDATE(),INTERVAL 0 DAY) AND
					notices.noticeExpires >= DATE_SUB(CURDATE(),INTERVAL 0 DAY) OR
					notices.isActive = 1
				ORDER BY
					orderDate";
    $smtRes = mysqli_query($mysqli, $sqlSmt) or die('-5' . mysqli_error());

	$qry = "SELECT
				emptasks.empTaskId,
				emptasks.createdBy,
				emptasks.taskTitle,
				emptasks.taskDesc,
				emptasks.taskPriority,
				DATE_FORMAT(emptasks.taskStart,'%b %d %Y') AS taskStart,
				DATE_FORMAT(emptasks.taskDue,'%b %d %Y') AS taskDue,
				UNIX_TIMESTAMP(emptasks.taskDue) AS orderDate,
				CONCAT(employees.empFirst,' ',employees.empLast) AS postedBy
			FROM
				emptasks
				LEFT JOIN employees ON emptasks.createdBy = employees.empId
			WHERE
				emptasks.assignedTo = ".$empId." AND
				emptasks.isClosed = 0
			ORDER BY
				orderDate
			LIMIT 3";
	$res = mysqli_query($mysqli, $qry) or die('-6'.mysqli_error());

	$stmt = "SELECT
				privatemessages.messageId,
				privatemessages.fromId,
				privatemessages.messageTitle,
				privatemessages.messageText,
				DATE_FORMAT(privatemessages.messageDate,'%b %d %Y') AS messageDate,
				UNIX_TIMESTAMP(privatemessages.messageDate) AS orderDate,
				CONCAT(employees.empFirst,' ',employees.empLast) AS sentBy
			FROM
				privatemessages
				LEFT JOIN employees ON privatemessages.fromId = employees.empId
			WHERE
				privatemessages.toId = ".$empId." AND
				privatemessages.toArchived = 0 AND
				privatemessages.toDeleted = 0
			ORDER BY
				orderDate DESC
			LIMIT 3";
	$result = mysqli_query($mysqli, $stmt) or die('-7'.mysqli_error());

	include ('includes/navigation.php');
?>
<div class="contentAlt">
	<div class="row">
		<div class="col-md-4">
			<div class="dashBlk">
				<div class="iconBlk primary">
					<i class="fa fa-envelope-o"></i>
				</div>
				<div class="contentBlk">
					<?php echo $messagesBox1; ?><br />
					<span class="msgCount" data-toggle="tooltip" data-placement="top" title="<?php echo $viewMessagesTooltip; ?>">
						<?php
							if ($unread > 0) {
								echo '<a href="index.php?page=inbox">'.$unread.'</a>';
							} else {
								echo '<a href="index.php?page=inbox">0</a>';
							}
						?>
					</span><br />
					<?php if ($unread == 1) { echo $messagesBox2; } else { echo $messagesBox3; }; ?>
				</div>
			</div>
		</div>

		<div class="col-md-4 col-dashBlk">
			<div class="dashBlk">
				<div class="iconBlk info">
					<i class="fa fa-calendar"></i>
				</div>
				<div class="contentBlk">
					<?php echo $timeBox1; ?><br />
					<span class="timeWorked" data-toggle="tooltip" data-placement="top" title="<?php echo $hoursMinsSecsTooltip; ?>"><?php echo $totalTime; ?></span><br />
					<?php echo $timeBox2; ?>
				</div>
			</div>
		</div>

		<div class="col-md-4 col-dashBlk">
			<div class="dashBlk">
				<div class="iconBlk success">
					<i class="fa fa-clock-o"></i>
				</div>
				<div class="contentBlk">
					<?php echo $clockBox; ?><br />
					<span class="clockstatus workStatus"></span>
					<form action="" method="post" class="clockBtn">
						<input type="hidden" name="clockId" value="<?php echo $clockId; ?>" />
						<input type="hidden" name="entryId" value="<?php echo $entryId; ?>" />
						<input type="hidden" name="weekNo" value="<?php echo $weekNum; ?>" />
						<input type="hidden" name="clockYear" value="<?php echo $currentYear; ?>" />
						<input type="hidden" name="running" id="running" value="<?php echo $running; ?>" />
						<input type="hidden" name="isRecord" id="isRecord" value="<?php echo $isRecord; ?>" />
						<button type="input" name="submit" id="timetrack" value="toggleTime" class="btn btn-lg btn-icon" value="toggleTime"><i class=""></i> <span></span></button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="contentAlt">
	<div class="row">
		<div class="col-md-6">
			<div class="content setHeight no-margin">
			<h4><?php echo $recentTasksTitle; ?></h4>
				<?php
					if(mysqli_num_rows($res) > 0) {
						while ($task = mysqli_fetch_assoc($res)) {
				?>
							<div class="task-item">
								<h4>
									<a href="index.php?page=viewTask&taskId=<?php echo $task['empTaskId']; ?>" data-toggle="tooltip" data-placement="right" title="<?php echo $viewTaskTooltip; ?>">
										<?php echo clean($task['taskTitle']); ?>
									</a>
									<span class="pull-right"><?php echo clean($task['taskPriority']); ?></span>
								</h4>
								<p class="infoLabels">
									<?php echo $createdByField.': '.clean($task['postedBy']); ?>
									<span class="pull-right"><?php echo $dateDueField.': '.$task['taskDue']; ?></span>
								</p>
								<p><?php echo ellipsis($task['taskDesc'],140); ?></p>
							</div>
				<?php
						}
					} else {
				?>
					<div class="alertMsg default">
						<i class="fa fa-minus-square-o"></i> <?php echo $noRecentTasksFound; ?>
					</div>
				<?php } ?>
			</div>
		</div>

		<div class="col-md-6">
			<div class="content setHeight no-margin">
			<h4><?php echo $recentMsgsTitle; ?></h4>
			<?php
					if(mysqli_num_rows($result) > 0) {
						while ($msg = mysqli_fetch_assoc($result)) {
				?>
							<div class="task-item">
								<h4>
									<a href="index.php?page=viewMessage&messageId=<?php echo $msg['messageId']; ?>" data-toggle="tooltip" data-placement="right" title="<?php echo $viewMsgTooltip; ?>">
										<?php echo clean($msg['messageTitle']); ?>
									</a>
								</h4>
								<p class="infoLabels">
									<?php echo $rcvdFromField.': '.clean($msg['sentBy']); ?>
									<span class="pull-right"><?php echo $dateRcvdField.': '.$msg['messageDate']; ?></span>
								</p>
								<p><?php echo ellipsis($msg['messageText'],140); ?></p>
							</div>
				<?php
						}
					} else {
				?>
					<div class="alertMsg default">
						<i class="fa fa-minus-square-o"></i> <?php echo $noRecentMsgFound; ?>
					</div>
				<?php } ?>
			</div>
		</div>
	</div>
</div>

<?php if(mysqli_num_rows($smtRes) > 0) { ?>
	<div class="contentAlt">
		<?php while ($note = mysqli_fetch_assoc($smtRes)) { ?>
			<div class="panel panel-info">
				<div class="panel-heading">
					<h3 class="panel-title">
						<i class="fa fa-bullhorn"></i> <?php echo clean($note['noticeTitle']); ?>
						<span class="pull-right"><?php echo $note['noticeDate']; ?></span>
					</h3>
				</div>
				<div class="panel-body notices">
					<p class="infoLabels"><?php echo $postedByField.': '.clean($note['postedBy']); ?></p>
					<p><?php echo clean($note['noticeText']); ?></p>
				</div>
			</div>
		<?php } ?>
	</div>
<?php } ?>