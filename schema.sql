-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 28 fév. 2026 à 15:50
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `service et produit`
--

-- --------------------------------------------------------

--
-- Structure de la table `facture`
--

CREATE TABLE `facture` (
  `id_facture` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_facture` date NOT NULL DEFAULT curdate(),
  `date_echeance` date NOT NULL,
  `id_service` int(11) DEFAULT NULL,
  `id_produit` int(11) DEFAULT NULL,
  `statut` varchar(20) DEFAULT 'en_attente',
  `numero_facture` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `facture`
--

INSERT INTO `facture` (`id_facture`, `montant`, `date_facture`, `date_echeance`, `id_service`, `id_produit`, `statut`, `numero_facture`) VALUES
(3, 5531.00, '2026-02-27', '2026-03-29', NULL, 1, 'impayee', '456665'),
(5, 5531.00, '2026-02-27', '2026-03-29', NULL, 1, 'impayee', 'fac12'),
(7, 5531.00, '2026-02-27', '2026-03-29', NULL, 1, 'impayee', 'fac-120-111'),
(10, 145.00, '2026-02-28', '2026-03-30', 11, NULL, 'en_attente', 'fac145'),
(11, 475.00, '2026-02-28', '2026-03-30', NULL, 10, 'impayee', 'facture77'),
(12, 145.00, '2026-02-28', '2026-03-30', 6, NULL, 'payee', 'fac44');

-- --------------------------------------------------------

--
-- Structure de la table `produit`
--

CREATE TABLE `produit` (
  `id_produit` int(11) NOT NULL,
  `nom_produit` varchar(100) NOT NULL,
  `type_produit` enum('carte_cadeau','carte_abonnement','carte_prepayee') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `code_unique` varchar(100) NOT NULL,
  `statut` enum('disponible','vendu','expire') DEFAULT 'disponible',
  `date_creation` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `produit`
--

INSERT INTO `produit` (`id_produit`, `nom_produit`, `type_produit`, `montant`, `code_unique`, `statut`, `date_creation`) VALUES
(1, 'carte prepayee', 'carte_cadeau', 444.00, 'CODE111', 'disponible', '2026-02-15'),
(2, 'abonnement', 'carte_abonnement', 555.00, 'CODE222', 'disponible', '2026-02-15'),
(3, 'carte cadeau', 'carte_abonnement', 884484.00, 'CODE777', 'disponible', '2026-02-16'),
(7, 'carte amazone', 'carte_prepayee', 15.00, 'code112', 'disponible', '2026-02-27'),
(10, 'carte amazon', 'carte_prepayee', 50.00, 'amz', 'disponible', '2026-01-29'),
(12, 'carte', 'carte_abonnement', 55.00, 'gik', 'disponible', '2026-01-29');

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

CREATE TABLE `service` (
  `id_service` int(11) NOT NULL,
  `nom_service` varchar(100) NOT NULL,
  `type_service` enum('abonnement','facture') NOT NULL,
  `tarif` decimal(10,2) NOT NULL,
  `frequence` enum('mensuel','annuel') DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('actif','suspendu','expire') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `service`
--

INSERT INTO `service` (`id_service`, `nom_service`, `type_service`, `tarif`, `frequence`, `date_debut`, `date_fin`, `statut`) VALUES
(5, 'facture', 'abonnement', 852.00, 'annuel', '2026-01-30', '2026-03-04', 'actif'),
(6, 'abonnement spotify', 'abonnement', 6674.00, 'mensuel', '2026-02-04', '2026-02-05', 'suspendu'),
(9, 'frais d\'inscriptions', 'abonnement', 4800.00, 'mensuel', '2026-01-28', '2026-02-18', 'actif'),
(11, 'abonement netflix', 'abonnement', 55.00, 'mensuel', '2026-01-26', '2026-03-07', 'suspendu'),
(13, 'carte', 'abonnement', 450.00, 'mensuel', '2026-01-29', '2026-03-03', 'suspendu'),
(15, 'hhhh', 'facture', 55.00, 'annuel', '2026-01-26', '2026-03-09', 'suspendu');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `facture`
--
ALTER TABLE `facture`
  ADD PRIMARY KEY (`id_facture`),
  ADD UNIQUE KEY `numero_facture` (`numero_facture`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date` (`date_facture`),
  ADD KEY `idx_echeance` (`date_echeance`),
  ADD KEY `facture_ibfk_1` (`id_service`),
  ADD KEY `facture_ibfk_2` (`id_produit`);

--
-- Index pour la table `produit`
--
ALTER TABLE `produit`
  ADD PRIMARY KEY (`id_produit`),
  ADD UNIQUE KEY `code_unique` (`code_unique`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`id_service`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `facture`
--
ALTER TABLE `facture`
  MODIFY `id_facture` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `produit`
--
ALTER TABLE `produit`
  MODIFY `id_produit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `service`
--
ALTER TABLE `service`
  MODIFY `id_service` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `facture`
--
ALTER TABLE `facture`
  ADD CONSTRAINT `facture_ibfk_1` FOREIGN KEY (`id_service`) REFERENCES `service` (`id_service`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `facture_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produit` (`id_produit`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@OLD_COLLATION_CONNECTION */;
