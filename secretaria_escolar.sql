-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 29/10/2026 às 18:11
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `secretaria_escolar`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos`
--

CREATE TABLE `alunos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `nascimento` date NOT NULL,
  `rg` varchar(20) NOT NULL,
  `cpf` char(11) NOT NULL,
  `telefone1` varchar(11) NOT NULL,
  `telefone2` varchar(11) DEFAULT NULL,
  `turma_id` int(10) UNSIGNED NOT NULL,
  `resp_nome` varchar(120) DEFAULT NULL,
  `resp_cpf` char(11) DEFAULT NULL,
  `resp_telefone` varchar(11) DEFAULT NULL,
  `resp_parentesco` varchar(30) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `alunos`
--

INSERT INTO `alunos` (`id`, `nome`, `nascimento`, `rg`, `cpf`, `telefone1`, `telefone2`, `turma_id`, `resp_nome`, `resp_cpf`, `resp_telefone`, `resp_parentesco`, `criado_em`) VALUES
(6, 'Eduardo', '2000-02-05', '44.555.444-5', '44455520899', '11999999999', NULL, 4, NULL, NULL, NULL, NULL, '2026-10-29 16:05:25');

-- --------------------------------------------------------

--
-- Estrutura para tabela `professor_turmas`
--

CREATE TABLE `professor_turmas` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `turma_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `professor_turmas`
--

INSERT INTO `professor_turmas` (`usuario_id`, `turma_id`) VALUES
(3, 4),
(3, 5);

-- --------------------------------------------------------

--
-- Estrutura para tabela `publicacoes`
--

CREATE TABLE `publicacoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `turma_id` int(10) UNSIGNED NOT NULL,
  `autor_id` int(10) UNSIGNED DEFAULT NULL,
  `tipo` enum('video','atividade') NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `prazo` date DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `turmas`
--

CREATE TABLE `turmas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(80) NOT NULL,
  `turno` enum('Manhã','Tarde','Noite') NOT NULL DEFAULT 'Manhã',
  `sala` varchar(20) NOT NULL DEFAULT '',
  `dias` varchar(40) NOT NULL DEFAULT '',
  `cor` char(7) NOT NULL DEFAULT '#3B4BDB',
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `turmas`
--

INSERT INTO `turmas` (`id`, `nome`, `turno`, `sala`, `dias`, `cor`, `criado_em`) VALUES
(4, 'INI 1', 'Manhã', '5', 'Seg,Qua,Qui', '#001aff', '2026-10-29 15:47:25'),
(5, 'DEG 1', 'Manhã', '3', 'Ter,Qua,Sex', '#3b4bdb', '2026-10-29 15:47:43'),
(6, 'PAV 1', 'Manhã', '1', 'Seg,Ter,Sex', '#3b4bdb', '2026-10-29 15:47:58');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `papel` enum('dev','adm','professor','aluno','responsavel') NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha_hash`, `papel`, `criado_em`) VALUES
(1, 'Desenvolvedor', 'dev@escola.com', '$2y$10$JFEJF5i0cY5jbOEHd7yq/.BDfCBePV7C3TPBr7af1iMhll2mD9clS', 'dev', '2026-10-29 16:00:05'),
(2, 'Eduardo', 'eduardolancioni23@gmail.com', '$2y$10$DlZjyoE6W3MTFKV3Pn3K9uvGxbo4n8SJqsH31qIcqSsqn4XJGGClK', 'aluno', '2026-10-29 16:07:59'),
(3, 'Professor', 'eduardolancioni33@gmail.com', '$2y$10$dn1XefT4/vPABW43PU88YOcaKdlyu5Zaak0SCPZDHqJcdqoyWBueW', 'professor', '2026-10-29 16:10:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `vinculos`
--

CREATE TABLE `vinculos` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `aluno_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `vinculos`
--

INSERT INTO `vinculos` (`usuario_id`, `aluno_id`) VALUES
(2, 6);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD KEY `fk_aluno_turma` (`turma_id`),
  ADD KEY `idx_aluno_nome` (`nome`);

--
-- Índices de tabela `professor_turmas`
--
ALTER TABLE `professor_turmas`
  ADD PRIMARY KEY (`usuario_id`,`turma_id`),
  ADD KEY `turma_id` (`turma_id`);

--
-- Índices de tabela `publicacoes`
--
ALTER TABLE `publicacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `turma_id` (`turma_id`),
  ADD KEY `autor_id` (`autor_id`);

--
-- Índices de tabela `turmas`
--
ALTER TABLE `turmas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `vinculos`
--
ALTER TABLE `vinculos`
  ADD PRIMARY KEY (`usuario_id`,`aluno_id`),
  ADD KEY `aluno_id` (`aluno_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `publicacoes`
--
ALTER TABLE `publicacoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `turmas`
--
ALTER TABLE `turmas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alunos`
--
ALTER TABLE `alunos`
  ADD CONSTRAINT `fk_aluno_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `professor_turmas`
--
ALTER TABLE `professor_turmas`
  ADD CONSTRAINT `professor_turmas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `professor_turmas_ibfk_2` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `publicacoes`
--
ALTER TABLE `publicacoes`
  ADD CONSTRAINT `publicacoes_ibfk_1` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publicacoes_ibfk_2` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `vinculos`
--
ALTER TABLE `vinculos`
  ADD CONSTRAINT `vinculos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vinculos_ibfk_2` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
