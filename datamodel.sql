DROP DATABASE IF EXISTS ssas;
CREATE DATABASE ssas;
USE ssas;

CREATE TABLE user(
    id INT AUTO_INCREMENT,
    username VARCHAR(256) UNIQUE,
    password VARCHAR(256) NOT NULL,
    PRIMARY KEY (id)
) engine='innodb';

CREATE TABLE image(
    id INT AUTO_INCREMENT,
    owner_id INT NOT NULL,
    image TEXT NOT NULL,
    PRIMARY KEY (id)
) engine='innodb';

CREATE TABLE shared_image(
    user_id INT NOT NULL,
    image_id INT NOT NULL,
    PRIMARY KEY(user_id, image_id),
    FOREIGN KEY fk_user (user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY fk_image (image_id) REFERENCES image(id) ON DELETE CASCADE
) engine='innodb';

CREATE TABLE post(
    id INT AUTO_INCREMENT,
    text TEXT NOT NULL,
    user_id INT NOT NULL,
    image_id INT NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY fk_user (user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY fk_image (image_id) REFERENCES image(id) ON DELETE CASCADE
) engine='innodb';


-- SELECT * FROM image
-- INNER JOIN shared_image
-- ON image_id = id
-- WHERE uid = user_id OR uid = owner_id

