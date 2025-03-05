<?php
 return array (
  'theme_default_i18n' => 'zh-CN',
  'theme_color' => '#319795',
  'theme_via' => '800e7d4a420094e8c376f6f1f7043162LH0Yf=',
  'custom_html' => '<script>
    window.chatwootSettings = { "position": "right", "type": "expanded_bubble", "launcherTitle": "客服" };
    (function (d, t) {
        Date.prototype.format = function () {
            const pad = (num) => String(num).padStart(2, \'0\');
            const yyyy = this.getFullYear();
            const mm = pad(this.getMonth() + 1);
            const dd = pad(this.getDate());
            const hh = pad(this.getHours());
            const min = pad(this.getMinutes());
            const ss = pad(this.getSeconds());
            return `${yyyy}-${mm}-${dd} ${hh}:${min}:${ss}`;
        };

        function getAuthorization() {
            return localStorage.getItem(\'authorization\');
        }
        async function getSubscribe() {
            const authorization = getAuthorization();
            if (!authorization) throw new Error(\'no auth\');;
            const response = await fetch(\'/api/v1/user/getSubscribe\', {
                method: \'GET\',
                headers: {
                    \'Authorization\': authorization,
                    \'Content-Type\': \'application/json\',
                }
            });
            if (!response.ok) {
                throw new Error(\'请求失败\');
            }
            const data = await response.json();
            return data;
        }

        async function setUser(e) {
            try {
                const subscribeData = await getSubscribe();
                const email = subscribeData.data.email
                const uuid = subscribeData.data.uuid
                const planName = subscribeData.data.plan.name
                const expired_at = subscribeData.data.expired_at
                const description = `套餐：${planName}_到期时间：${new Date(expired_at * 1000).format()}`
                window.$chatwoot.setUser(uuid, {
                    name: email,
                    email: email,
                    description: description
                })
                console.log(\'Chatwoot setUser finished\')
            } catch (error) {
                console.log(\'Chatwoot setUser\', error)
            }
        }
        var BASE_URL = "https://chat.02000.net";
        var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
        g.src = BASE_URL + "/packs/js/sdk.js";
        g.defer = true;
        g.async = true;
        s.parentNode.insertBefore(g, s);
        g.onload = function () {
            window.chatwootSDK.run({
                websiteToken: \'ALiYU5kaAV3jJAxW7kVkipED\',
                baseUrl: BASE_URL
            })
            window.addEventListener(\'chatwoot:ready\', setUser)
            window.addEventListener(\'chatwoot:on-start-conversation\', setUser)
            window.addEventListener(\'chatwoot:on-message\', setUser)
        }
    })(document, "script");
</script>',
) ;