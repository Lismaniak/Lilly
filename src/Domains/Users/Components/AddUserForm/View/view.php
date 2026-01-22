<section class="users-add-user-form">
    <header class="users-add-user-form__header">
        <h2>Add User</h2>
        <p>Create a new user record for this domain.</p>
    </header>

    <?php if (!empty($props->notice)) : ?>
        <div class="users-add-user-form__notice">
            <?= htmlspecialchars($props->notice, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form class="users-add-user-form__form" method="post" action="<?= htmlspecialchars($props->actionPath, ENT_QUOTES, 'UTF-8') ?>">
        <label class="users-add-user-form__label" for="users-add-user-form-name">Name</label>
        <input class="users-add-user-form__input" id="users-add-user-form-name" name="name" type="text" placeholder="Ada Lovelace" required />
        <button class="users-add-user-form__button" type="submit">Create user</button>
    </form>
</section>
