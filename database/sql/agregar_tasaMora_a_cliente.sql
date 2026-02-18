-- Agregar columna TasaMoraID a la tabla Cliente
ALTER TABLE `Cliente` 
ADD COLUMN `TasaMoraID` INT DEFAULT NULL AFTER `TasaID`,
ADD FOREIGN KEY (`TasaMoraID`) REFERENCES `TasaMora`(`TasaMoraID`);
