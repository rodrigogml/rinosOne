export const ptBR = {
    access: {
        brand: 'Rinos One',
        entryTitle: 'Acesse sua conta', createTitle: 'Criar conta', haveAccount: 'Já tenho uma conta', createAccountLink: 'Criar conta',
        entryDescription: 'Crie uma conta ou escolha como deseja entrar.',
        offline: 'Você está sem conexão. As ações estarão disponíveis quando a rede voltar.',
        modeLabel: 'Modo de acesso',
        modes: { register: 'Criar conta', password: 'Entrar', passwordless: 'Sem senha' },
        fields: {
            email: 'E-mail', password: 'Senha', rememberMe: 'Manter-me conectado', code: 'Código de confirmação',
            displayName: 'Nome', newPassword: 'Nova senha',
        },
        actions: {
            processing: 'Processando…', createAccount: 'Criar conta', enter: 'Entrar', sendAccessCode: 'Entrar sem senha',
            confirming: 'Confirmando…', confirmLink: 'Confirmar link', completeAccess: 'Concluir acesso', resendMessage: 'Reenviar mensagem',
            returnToAccess: 'Voltar ao acesso', definePassword: 'Definir senha', savePassword: 'Salvar senha',
            invalidateOthers: 'Invalidar outras sessões', endSession: 'Encerrar esta sessão', cancel: 'Cancelar', invalidateSessions: 'Invalidar sessões',
        },
        confirmation: {
            title: 'Confirme seu e-mail', body: 'Digite o código de 6 dígitos enviado na mensagem.', validFor: 'Válido por {time}.',
            remembered: 'Você escolheu permanecer conectado neste navegador.',
        },
        security: {
            title: 'Segurança de acesso', greeting: 'Olá, {name}.', passwordDefined: 'Senha definida.',
            passwordOptional: 'Você pode definir uma senha opcional.', passwordHelp: 'Mínimo de 6 caracteres e duas categorias: minúscula, maiúscula, número ou símbolo.',
            sessions: 'Sessões', persistent: 'Você permanecerá conectado neste navegador.', active: 'Sessão atual ativa.',
            invalidateTitle: 'Invalidar outras sessões?', invalidateDescription: 'Os outros navegadores e dispositivos precisarão entrar novamente.',
        },
        presentation: {
            visualPreferences: 'Preferências visuais', language: 'Idioma', theme: 'Tema', light: 'Claro', dark: 'Escuro',
            textDensity: 'Densidade do texto', spacing: 'Espaçamento', componentSize: 'Tamanho dos elementos',
            compact: 'Compacto', default: 'Padrão', comfortable: 'Confortável', large: 'Amplo', selectedLanguage: 'Idioma atual: {language}',
        },
        shell: { menu: 'Menu pessoal', avatar: 'Menu pessoal de {name}', userSettings: 'Configurações do usuário', settingsUnavailable: 'Disponível em breve', signOut: 'Sair', openNavigation: 'Abrir navegação', closeNavigation: 'Fechar navegação', navigation: 'Navegação', emptyNavigation: 'Nenhuma área adicional está disponível nesta fase.' },
        tenant: { title: 'Organização atual', managementTitle: 'Organizações', selectAvatar: 'Selecionar organização', currentAvatar: 'Organização atual: {name}', current: 'Contexto:', noneSelected: 'Nenhuma selecionada', name: 'Nome da organização', nameRequired: 'Informe o nome da organização.', nameTooLong: 'O nome deve ter no máximo 120 caracteres.', loading: 'Carregando organizações…', empty: 'Ainda não há organizações.', personalSpace: 'Usar somente meu espaço', create: 'Criar organização', manage: 'Gerenciar organizações', close: 'Fechar seletor de organização', selected: 'Organização ativa: {name}.', personalSelected: 'Você está usando somente seu espaço.', creationAccepted: 'A organização está sendo preparada.', createFailed: 'Não foi possível criar esta organização.', availabilityChanged: 'A disponibilidade foi atualizada.', availabilityFailed: 'Não foi possível atualizar a disponibilidade.', enable: 'Habilitar', disable: 'Desabilitar', disableConfirmationTitle: 'Desabilitar organização?', disableConfirmationDescription: 'A organização {name} não poderá ser selecionada até ser habilitada novamente.', states: { PROVISIONING: 'Preparando', ACTIVE: 'Ativa', INACTIVE: 'Desabilitada', FAILED: 'Indisponível' }, offline: 'Você está sem conexão. Tente novamente quando a rede voltar.', loadFailed: 'Não foi possível carregar as organizações.', selectFailed: 'Não foi possível selecionar esta organização.', endFailed: 'Não foi possível encerrar este contexto.' },
        feedback: {
            emailInvalid: 'Informe um e-mail válido.', displayNameRequired: 'Informe um nome de exibição.', verificationSent: 'Verifique seu e-mail para continuar.', codeExpired: 'Este código expirou. Solicite uma nova mensagem para continuar.',
            accessSuccess: 'Acesso concluído com sucesso.', requestFailed: 'Não foi possível concluir esta ação.',
            confirmationFailed: 'Não foi possível confirmar este código.', confirmationInvalid: 'Este código ou link não é mais válido. Solicite uma nova mensagem para continuar.',
            confirmationRateLimited: 'Não é possível concluir esta ação agora. Aguarde alguns minutos e tente novamente.',
            passwordCriteria: 'A senha não atende aos critérios: mínimo de 6 caracteres e duas categorias.', passwordRequired: 'Informe uma senha para continuar.', passwordSet: 'Senha definida.',
            invalidateFailed: 'Não foi possível invalidar as outras sessões.', invalidateSuccess: 'Outras sessões foram invalidadas.',
        },
    },
} as const;
