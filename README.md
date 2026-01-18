# 🍔 Sistema de Gestão de Cantina Escolar

Um sistema web completo para gerenciamento de cantinas escolares, facilitando a interação entre pais, alunos e a administração da cantina. O sistema permite recargas de saldo online via Mercado Pago, definição de limites de gastos diários, controle de estoque e um PDV (Ponto de Venda) ágil.

## 📋 Sobre o Projeto

Este projeto visa modernizar o processo de compra e venda em cantinas escolares, eliminando o uso de dinheiro físico pelos alunos e oferecendo aos pais controle total sobre a alimentação e gastos de seus filhos.

### ✨ Principais Funcionalidades

#### 👨‍👩‍👧‍👦 Para os Pais/Responsáveis
- **Gestão de Dependentes:** Cadastro e visualização do perfil dos filhos.
- **Recargas Online:** Adição de saldo na carteira digital do aluno via **Mercado Pago** (Pix, Boleto, Cartão).
- **Controle Financeiro:** Definição de limites diários de gastos por aluno.
- **Histórico:** Visualização completa do histórico de compras e recargas.
- **Co-responsáveis:** Funcionalidade para adicionar outro responsável financeiro (Co-Parent).

#### 🎓 Para os Alunos
- **Painel do Aluno:** Visualização de saldo atual e histórico de consumo.
- **Perfil:** Edição de dados básicos (com restrições).

#### 🛡️ Para a Administração (Cantina)
- **Dashboard:** Visão geral de vendas, faturamento e usuários.
- **PDV (Ponto de Venda):** Interface rápida para realizar vendas, com busca de produtos e identificação de alunos (possivelmente via Tags/QR Code).
- **Gestão de Produtos:** Cadastro, edição e controle de estoque de lanches e bebidas.
- **Financeiro:** Relatórios de vendas, recargas e estornos.
- **Gerenciamento de Tags:** Associação de tags (cartões/pulseiras) aos alunos.

---

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP (Estruturado/Vanilla)
- **Frontend:** HTML5, CSS3, JavaScript (com jQuery)
- **Banco de Dados:** MySQL / MariaDB
- **Pagamentos:** [SDK do Mercado Pago](https://github.com/AndyTargino/MercadoPago-API) (PHP)
- **Servidor Web:** Apache/Nginx (Recomendado via XAMPP/WAMP para local)

---

## ⚙️ Instalação e Configuração

Siga os passos abaixo para rodar o projeto em seu ambiente local.

### 1. Clonar o Repositório
```bash
git clone https://github.com/nyshimura/cantina.git
cd cantina
```

### 2. Importar a Base de dados
```bash 
database.sql
```
