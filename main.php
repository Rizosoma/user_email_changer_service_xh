<?php

/**
 *
 */
class UserEmailChangerService
{
    /**
     * @var PDO
     */
    private \PDO $db;
    /**
     * @var UserEmailSenderInterface
     */
    private UserEmailSenderInterface $emailSender;
    /**
     * @var array
     */
    private array $listeners = [];

    /**
     * @param PDO $db
     * @param UserEmailSenderInterface $emailSender
     */
    public function __construct(\PDO $db, UserEmailSenderInterface $emailSender)
    {
        $this->db = $db;
        $this->emailSender = $emailSender;
    }

    /**
     * @param UserChangeListenerInterface $listener
     * @return void
     */
    public function addListener(UserChangeListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * @param int $userId
     * @param string $email
     *
     * @return void
     *
     * @throws \PDOException
     * @throws EmailSendException
     */
    public function changeEmail(int $userId, string $email): void
    {
        $this->db->beginTransaction();

        try {
            $oldEmail = $this->getUserEmailById($userId);

            $statement = $this->db->prepare('UPDATE users SET email = :email WHERE id = :id');
            $statement->bindParam(':id', $userId, \PDO::PARAM_INT);
            $statement->bindParam(':email', $email, \PDO::PARAM_STR);
            $statement->execute();

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        foreach ($this->listeners as $listener) {
            // Внутри метода onUserEmailChange может быть реализована отправка сообщения в очередь,
            // чтобы этот процесс выполнялся асинхронно.
            $listener->onUserEmailChange($userId, $oldEmail, $email);
        }
    }

    /**
     * @param int $userId
     *
     * @return string
     *
     * @throws \PDOException
     */
    private function getUserEmailById(int $userId): string
    {
        $statement = $this->db->prepare('SELECT email FROM users WHERE id = :id FOR UPDATE');
        $statement->bindParam(':id', $userId, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchColumn();
    }
}
