CREATE TABLE `canvases` (
  `canva_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unique_canva_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `background_type` enum('solid','image') DEFAULT 'solid',
  `background_value` varchar(255) DEFAULT NULL,
  `canva_category` varchar(50) NOT NULL,
  `copy_from_group_id` int(11) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `access_type` varchar(20) DEFAULT 'private',
  `share_token` varchar(32) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `token_access_type` enum('view','edit') DEFAULT 'view'
);

CREATE TABLE `canvas_collaborators` (
  `id` int(11) NOT NULL,
  `canva_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` enum('view','edit') DEFAULT 'edit',
  `accepted_at` datetime DEFAULT NULL,
  `status` enum('pending','accepted') DEFAULT 'accepted',
  `invited_by` int(11) DEFAULT NULL,
  `invited_at` datetime DEFAULT current_timestamp(),
  `owner_name` varchar(100) DEFAULT NULL,
  `can_edit_notes` tinyint(1) DEFAULT 1,
  `can_delete_notes` tinyint(1) DEFAULT 0
);

CREATE TABLE `drawings` (
  `id` int(11) NOT NULL,
  `image` longblob NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
CREATE TABLE `email_verifications` (
  `email_verifications_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(32) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'unused'
);
CREATE TABLE `groups` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
CREATE TABLE `group_members` (
  `group_member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('viewer','editor','admin','owner') DEFAULT 'viewer',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
);
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` varchar(255) NOT NULL
);
CREATE TABLE `media` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('image','video','file','text') NOT NULL,
  `data` text NOT NULL,
  `position_x` int(11) DEFAULT 100,
  `position_y` int(11) DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `comment` text DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `canva_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL
);

CREATE TABLE `notes` (
  `note_id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `note_type` enum('text','image','video','file') DEFAULT 'text',
  `status` enum('draft','active','archived') DEFAULT 'draft',
  `font` varchar(50) DEFAULT NULL,
  `recipient` varchar(255) NOT NULL,
  `font_color` varchar(20) DEFAULT '#000000',
  `background_color` varchar(20) DEFAULT '#FFFFFF',
  `height` int(11) DEFAULT 200,
  `width` int(11) DEFAULT 300,
  `tag` text DEFAULT NULL,
  `created_date` date NOT NULL DEFAULT curdate(),
  `due_date` date DEFAULT NULL,
  `icon` varchar(10) DEFAULT NULL,
  `font_size` int(11) DEFAULT 14,
  `color` varchar(20) DEFAULT '#ffffff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `position_x` int(11) DEFAULT 0,
  `position_y` int(11) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `canva_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `locked_by_name` varchar(255) DEFAULT NULL
);

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
);
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 1 hour),
  `reset_id` int(11) NOT NULL
);
CREATE TABLE `shared_canvases` (
  `shared_canvases_id` int(11) NOT NULL,
  `canva_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `permission` enum('view','edit') DEFAULT 'view',
  `shared_at` datetime DEFAULT current_timestamp()
);

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `due_date` date NOT NULL,
  `priority` enum('Low','Medium','High') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
);
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('teacher','student','guest','admin') NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `isactive` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `avatar` text NOT NULL,
  `color` varchar(7) DEFAULT '#ff0000',
  `notifications_pref` varchar(255) DEFAULT 'all',
  `role_id` int(11) DEFAULT 1,
  `public_token` varchar(100) DEFAULT NULL
);

CREATE TABLE `user_cursors` (
  `user_id` int(11) NOT NULL,
  `canva_id` int(11) NOT NULL,
  `x` int(11) NOT NULL,
  `y` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_active` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
);

CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `language` varchar(5) DEFAULT 'en',
  `theme` varchar(10) DEFAULT 'light',
  `notifications` tinyint(1) DEFAULT 1,
  `timezone` varchar(50) DEFAULT 'UTC'
);


ALTER TABLE `canvases`
  ADD PRIMARY KEY (`canva_id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Ευρετήρια για πίνακα `canvas_collaborators`
--
ALTER TABLE `canvas_collaborators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `canva_id` (`canva_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `invited_by` (`invited_by`);

--
-- Ευρετήρια για πίνακα `drawings`
--
ALTER TABLE `drawings`
  ADD PRIMARY KEY (`id`);

--
-- Ευρετήρια για πίνακα `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`email_verifications_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Ευρετήρια για πίνακα `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Ευρετήρια για πίνακα `group_members`
--
ALTER TABLE `group_members`
  ADD PRIMARY KEY (`group_member_id`),
  ADD UNIQUE KEY `unique_membership` (`group_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Ευρετήρια για πίνακα `group_tasks`
--
ALTER TABLE `group_tasks`
  ADD PRIMARY KEY (`id`);

--
-- Ευρετήρια για πίνακα `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `canva_id` (`canva_id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `fk_media_locked_by` (`locked_by`);

--
-- Ευρετήρια για πίνακα `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `canva_id` (`canva_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `group_id` (`group_id`);

--
-- Ευρετήρια για πίνακα `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Ευρετήρια για πίνακα `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`);

--
-- Ευρετήρια για πίνακα `shared_canvases`
--
ALTER TABLE `shared_canvases`
  ADD PRIMARY KEY (`shared_canvases_id`);

--
-- Ευρετήρια για πίνακα `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Ευρετήρια για πίνακα `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Ευρετήρια για πίνακα `user_cursors`
--
ALTER TABLE `user_cursors`
  ADD PRIMARY KEY (`user_id`,`canva_id`);

--
-- Ευρετήρια για πίνακα `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT για άχρηστους πίνακες
--

--
-- AUTO_INCREMENT για πίνακα `canvases`
--
ALTER TABLE `canvases`
  MODIFY `canva_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT για πίνακα `canvas_collaborators`
--
ALTER TABLE `canvas_collaborators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT για πίνακα `drawings`
--
ALTER TABLE `drawings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT για πίνακα `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `email_verifications_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT για πίνακα `groups`
--
ALTER TABLE `groups`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT για πίνακα `group_members`
--
ALTER TABLE `group_members`
  MODIFY `group_member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT για πίνακα `group_tasks`
--
ALTER TABLE `group_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT για πίνακα `media`
--
ALTER TABLE `media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- AUTO_INCREMENT για πίνακα `notes`
--
ALTER TABLE `notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT για πίνακα `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT για πίνακα `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT για πίνακα `shared_canvases`
--
ALTER TABLE `shared_canvases`
  MODIFY `shared_canvases_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT για πίνακα `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT για πίνακα `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT για πίνακα `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Περιορισμοί για άχρηστους πίνακες
--

--
-- Περιορισμοί για πίνακα `canvases`
--
ALTER TABLE `canvases`
  ADD CONSTRAINT `canvases_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `canvases_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`);

--
-- Περιορισμοί για πίνακα `canvas_collaborators`
--
ALTER TABLE `canvas_collaborators`
  ADD CONSTRAINT `canvas_collaborators_ibfk_1` FOREIGN KEY (`canva_id`) REFERENCES `canvases` (`canva_id`),
  ADD CONSTRAINT `canvas_collaborators_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `canvas_collaborators_ibfk_3` FOREIGN KEY (`invited_by`) REFERENCES `users` (`user_id`);

--
-- Περιορισμοί για πίνακα `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Περιορισμοί για πίνακα `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Περιορισμοί για πίνακα `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Περιορισμοί για πίνακα `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`canva_id`) REFERENCES `canvases` (`canva_id`),
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notes_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `notes_ibfk_4` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`);

--
-- Περιορισμοί για πίνακα `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Περιορισμοί για πίνακα `user_cursors`
--
ALTER TABLE `user_cursors`
  ADD CONSTRAINT `user_cursors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Περιορισμοί για πίνακα `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;
