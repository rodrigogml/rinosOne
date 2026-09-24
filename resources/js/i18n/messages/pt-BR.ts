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
