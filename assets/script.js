new Vue({
    el: '#app',
    data() {
        const user = {
            name: '',
            status: 'online',
            uid: isServer ? 'server' : '_' + Math.random().toString(36).substr(2, 9),
            ip: '',
        };

        return {
            user: user,
            usersList: [],
            socket: null,
            enterButton: true,
            chats: {
                'group': [],
            },
            chat: 'group',
            messages: [],
            message: '',
            log: '',
        }
    },
    mounted() {
        if (isServer) {
            this.user.name = 'server';
            this.enter();
        }
    },
    methods: {
        enter() {
            if (this.user.name.trim().length === 0) {
                alert('Digite seu nome de usuário');
                return;
            }

            this.setupSocket();
            this.enterButton = false;
        },
        leave() {
            this.socket.close();
            this.enterButton = true;
        },
        send(data) {
            this.socket.send(JSON.stringify(data));
        },
        sendMessage() {
            if (this.message.trim().length === 0) {
                alert('Escreva alguma mensagem');
            }
            let to = null;
            if (this.chat !== 'group') {
                to = this.getUser(this.chat);
            }
            const messageData = {
                type: 'message',
                user: this.user,
                value: this.message,
                to: to,
                date: Date.now(),
            };
            // Serializamos o objeto para json
            this.socket.send(JSON.stringify(messageData));
            this.message = '';
        },
        triggerFile() {
            document.getElementById('file').click();
        },
        sendFile(event) {
            let files = event.target.files;
            if (files.length > 0) {
                let to = null;
                if (this.chat !== 'group') {
                    to = this.getUser(this.chat);
                }
                const file = files[0];
                var reader = new FileReader();
                reader.readAsDataURL(file);
                let that = this;
                reader.onload = () => {
                    const fdata = {
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        file: reader.result,
                    };
                    const messageData = {
                        type: 'file',
                        user: this.user,
                        value: fdata,
                        to: to,
                        date: Date.now(),
                    };
                    console.log(messageData);
                    // Serializamos o objeto para json
                    that.socket.send(JSON.stringify(messageData));
                };
                reader.onerror = function (error) {
                    console.log('Error: ', error);
                    alert('Não foi possível enviar o arquivo, tente novamente');
                };
            }
        },
        setChat(chat) {
            this.chat = chat;
            this.message = '';
            this.messages = this.chats[chat];
        },
        getUser(uid) {
            for(let i = 0; i < this.usersList.length; i++) {
                if (this.usersList[i].uid === uid) {
                    return this.usersList[i];
                }
            }
            return null;
        },
        getUserName(uid) {
            let u = this.getUser(uid);
            if (u) {
                return u.name;
            }
            return null;
        },
        lastMessage(uid) {
            if (typeof this.chats[uid] !== 'undefined' && this.chats[uid].length > 0) {
                let alen = this.chats[uid].length - 1;
                let a = this.chats[uid][alen];
                if (a.type === 'message') {
                    return (a.value.length > 10) ? a.value.substring(0, 10) + '...' : a.value;
                }
                if (a.type === 'file') {
                    return 'Arquivo';
                }
            }
            return 'Inicie a conversa!';
        },
        sort(list) {
            return list.sort((a, b) => {
                if (typeof this.chats[a.uid] !== 'undefined' && this.chats[a.uid].length > 0) {
                    if (typeof this.chats[b.uid] !== 'undefined' && this.chats[b.uid].length > 0) {
                        let alen = this.chats[a.uid].length - 1;
                        let blen = this.chats[b.uid].length - 1;
                        return this.chats[a.uid][alen].date > this.chats[b.uid][blen].date ? -1 : 1;
                    }
                    return -1;
                } else if (typeof this.chats[b.uid] !== 'undefined' && this.chats[b.uid].length > 0) {
                    return 1;
                }

                if (a.status === 'online' && b.status === 'offline') {
                    return -1;
                } else if (a.status === 'offline' && b.status === 'online') {
                    return 1;
                }

                if (a.name < b.name) {
                    return -1;
                } else if (a.name > b.name) {
                    return 1;
                }
                return 0;
            });
        },
        setupSocket() {
            this.socket = new WebSocket('ws://localhost:9990/chat');
            this.socket.onopen = () => {
                const enterData = {
                    type: 'enter',
                    user: this.user,
                    value: null,
                    to: null,
                    date: Date.now(),
                };
                this.send(enterData);
            };

            this.socket.addEventListener('message', (event) => {
                // Deserializamos o objeto
                const data = JSON.parse(event.data);
                console.log(data);
                switch (data.type) {
                    case 'users':
                        this.usersList = this.sort(data.value);
                        for (let i = 0; i < this.usersList.length; i++) {
                            if (typeof this.chats[this.usersList[i].uid] === 'undefined') {
                                this.chats[this.usersList[i].uid] = [];
                            }
                        }
                        break;
                    case 'leave':
                        break;
                    case 'enter':
                        if (this.user.uid === data.value.uid) {
                            this.user.ip = data.value.ip;
                        }
                        break;
                    case 'message':
                    case 'file':
                        let chatid = 'group';
                        if (data.to === null) {
                            this.chats['group'].push(data);
                            chatid = 'group';
                        } else {
                            if (data.user.uid === this.user.uid) {
                                this.chats[data.to.uid].push(data);
                                chatid = data.to.uid;
                            } else {
                                this.chats[data.user.uid].push(data);
                                chatid = data.user.uid;
                            }
                        }
                        if (chatid === this.chat) {
                            this.messages = this.chats[chatid];
                        }
                        this.usersList = this.sort(this.usersList);
                        break;
                    case 'log':
                        this.log = data.value;
                        break;
                }
            });
        },
    },
});

Vue.use(VueTimeago, {
    locale: 'pt-BR',
    locales: {
        'pt-BR': [
            "agora mesmo",
            ["%s segundo atrás", "%s segundos atrás"],
            ["%s minuto atrás", "%s minutos atrás"],
            ["%s hora atrás", "%s horas atrás"],
            ["%s dia atrás", "%s dias atrás"],
            ["%s semana atrás", "%s semanas atrás"],
            ["%s mês atrás", "%s meses atrás"],
            ["%s ano atrás", "%s anos atrás"]
        ],
    }
});
Vue.config.devtools = true;
